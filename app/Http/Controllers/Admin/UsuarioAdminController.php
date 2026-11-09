<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PerfilPrestador;
use App\Models\User;
use App\Support\Auditoria;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;

class UsuarioAdminController extends Controller
{
    /**
     * Muestra el listado de usuarios y sus roles asignados.
     */
    public function index(): Response
    {
        $usuarios = User::with('roles')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        // Un admin no debe poder ofrecer ni asignar 'superadmin'.
        $roles = $this->rolesAsignables(Auth::user());

        return Inertia::render('Admin/Usuarios/Index', [
            'usuarios' => $usuarios,
            'roles'    => $roles,
        ]);
    }

    /**
     * Registra un nuevo usuario administrativo con su rol.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'nombre'   => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users,email',
            'password' => ['required', Rules\Password::defaults()],
            'role'     => ['required', 'string', Rule::in($this->rolesAsignables($request->user()))],
        ]);

        DB::transaction(function () use ($request) {
            $user = User::create([
                'nombre'   => $request->nombre,
                'email'    => $request->email,
                'password' => Hash::make($request->password),
            ]);

            $user->assignRole($request->role);
            $this->asegurarPerfilSiEsPrestador($user, $request->role);

            Auditoria::registrar('usuario.creado', 'user', $user->id, null, ['rol' => $request->role]);
        });

        return redirect()->back()->with('success', 'Usuario administrativo creado exitosamente.');
    }

    /**
     * Actualiza la información del usuario y su rol.
     *
     * Reglas de seguridad:
     *  - Todo se valida ANTES de escribir y se guarda en una sola transacción.
     *  - Solo un superadmin puede modificar a otro superadmin o asignar ese rol.
     *  - Nadie puede cambiarse su propio rol (evita auto-escalar o auto-bloquearse).
     *  - Siempre debe quedar al menos un administrador.
     *  - Si cambian credenciales o rol, se cierran las sesiones abiertas de ese usuario.
     */
    public function update(Request $request, User $usuario): RedirectResponse
    {
        $actor = $request->user();

        if ($bloqueo = $this->bloqueoPorJerarquia($actor, $usuario)) {
            return redirect()->back()->with('error', $bloqueo);
        }

        $datos = $request->validate([
            'nombre'   => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($usuario->id)],
            'role'     => ['required', 'string', Rule::in($this->rolesAsignables($actor))],
            'password' => ['nullable', Rules\Password::defaults()],
        ]);

        $rolNuevo    = $datos['role'];
        $rolAnterior = $usuario->getRoleNames()->all();
        $cambiaRol   = $rolAnterior !== [$rolNuevo];
        $esUnoMismo  = $usuario->id === $actor->id;

        if ($esUnoMismo && $cambiaRol) {
            return redirect()->back()->with('error', 'No puedes cambiar tu propio rol.');
        }

        if ($cambiaRol && $this->esAdministrador($usuario) && ! in_array($rolNuevo, self::ROLES_ADMIN, true) && ! $this->existeOtroAdministrador($usuario)) {
            return redirect()->back()->with('error', 'Debe quedar al menos un administrador en el sistema.');
        }

        $cambiaPassword  = filled($datos['password'] ?? null);
        $cambiaEmail     = $usuario->email !== $datos['email'];
        $cambiaCredencial = $cambiaPassword || $cambiaEmail || $cambiaRol;

        DB::transaction(function () use ($usuario, $datos, $rolNuevo, $rolAnterior, $cambiaPassword, $cambiaEmail, $cambiaCredencial) {
            $usuario->nombre = $datos['nombre'];
            $usuario->email  = $datos['email'];

            if ($cambiaPassword) {
                $usuario->password = Hash::make($datos['password']);
            }

            if ($cambiaCredencial) {
                $usuario->remember_token = null; // invalida "recordarme"
            }

            $usuario->save();

            $usuario->syncRoles([$rolNuevo]);
            $this->asegurarPerfilSiEsPrestador($usuario, $rolNuevo);

            // Sin contraseñas ni correos: solo qué cambió.
            Auditoria::registrar(
                'usuario.actualizado',
                'user',
                $usuario->id,
                ['roles' => $rolAnterior],
                [
                    'roles'             => [$rolNuevo],
                    'email_cambiado'    => $cambiaEmail,
                    'password_cambiada' => $cambiaPassword,
                ],
            );
        });

        // Fuera de la transacción; si es uno mismo no se cierra su propia sesión.
        if ($cambiaCredencial && ! $esUnoMismo) {
            $this->cerrarSesionesDe($usuario);
        }

        return redirect()->back()->with('success', 'Usuario y permisos actualizados.');
    }

    /**
     * Elimina a un usuario del sistema.
     */
    public function destroy(User $usuario): RedirectResponse
    {
        $actor = Auth::user();

        if ($usuario->id === $actor->id) {
            return redirect()->back()->with('error', 'No puedes eliminar tu propia cuenta.');
        }

        if ($bloqueo = $this->bloqueoPorJerarquia($actor, $usuario)) {
            return redirect()->back()->with('error', $bloqueo);
        }

        if ($this->esAdministrador($usuario) && ! $this->existeOtroAdministrador($usuario)) {
            return redirect()->back()->with('error', 'Debe quedar al menos un administrador en el sistema.');
        }

        $roles = $usuario->getRoleNames()->all();

        try {
            DB::transaction(function () use ($usuario, $roles) {
                $usuario->delete();
                Auditoria::registrar('usuario.eliminado', 'user', $usuario->id, ['roles' => $roles], null);
            });
        } catch (QueryException) {
            // Llaves foráneas (pedidos, servicios, reseñas...): sin detalles técnicos al usuario.
            return redirect()->back()->with('error', 'No se puede eliminar: el usuario tiene pedidos u otros registros asociados.');
        }

        return redirect()->back()->with('success', 'Usuario eliminado correctamente.');
    }

    private const ROLES_ADMIN = ['admin', 'superadmin'];

    /** Roles que el actor puede asignar: solo un superadmin puede asignar 'superadmin'. */
    private function rolesAsignables(User $actor): array
    {
        return Role::pluck('name')
            ->when(! $actor->hasRole('superadmin'), fn ($roles) => $roles->reject(fn ($r) => $r === 'superadmin'))
            ->values()
            ->all();
    }

    /** Un admin no puede tocar a un superadmin. */
    private function bloqueoPorJerarquia(User $actor, User $objetivo): ?string
    {
        if ($objetivo->hasRole('superadmin') && ! $actor->hasRole('superadmin')) {
            return 'Solo un superadministrador puede modificar a otro superadministrador.';
        }

        return null;
    }

    private function esAdministrador(User $usuario): bool
    {
        return $usuario->hasAnyRole(self::ROLES_ADMIN);
    }

    /** whereHas (no User::role()) porque role() lanza excepción si un rol aún no existe en la BD. */
    private function existeOtroAdministrador(User $excluido): bool
    {
        return User::where('id', '!=', $excluido->id)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', self::ROLES_ADMIN))
            ->exists();
    }

    /** Cierra las sesiones abiertas del usuario (solo aplica con SESSION_DRIVER=database). */
    private function cerrarSesionesDe(User $usuario): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $usuario->id)->delete();
        }
    }

    /**
     * Un prestador sin perfil no puede operar (el dashboard lo trataría como cliente).
     * Aquí el perfil se crea por una acción explícita del admin, nunca por navegar.
     */
    private function asegurarPerfilSiEsPrestador(User $user, string $rol): void
    {
        if ($rol === 'prestador') {
            PerfilPrestador::firstOrCreate(
                ['user_id' => $user->id],
                ['estado_verificacion' => PerfilPrestador::ESTADO_PENDIENTE]
            );
        }
    }
}