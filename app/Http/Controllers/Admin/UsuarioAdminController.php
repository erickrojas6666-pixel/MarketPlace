<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PerfilPrestador;
use App\Models\User;
use App\Support\Auditoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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

        $roles = Role::pluck('name');

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
            'role'     => 'required|string|exists:roles,name',
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
     */
    public function update(Request $request, User $usuario): RedirectResponse
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $usuario->id,
            'role'  => 'required|string|exists:roles,name',
        ]);

        $rolAnterior = $usuario->getRoleNames()->all();

        $usuario->update([
            'nombre' => $request->nombre,
            'email'  => $request->email,
        ]);

        if ($request->filled('password')) {
            $request->validate([
                'password' => ['nullable', Rules\Password::defaults()],
            ]);
            $usuario->update([
                'password' => Hash::make($request->password),
            ]);
        }

        // Sincronizar nuevo rol con Spatie
        $usuario->syncRoles([$request->role]);
        $this->asegurarPerfilSiEsPrestador($usuario, $request->role);

        // Sin contraseñas: solo quién cambió y qué rol tenía/tiene.
        Auditoria::registrar(
            'usuario.actualizado',
            'user',
            $usuario->id,
            ['roles' => $rolAnterior],
            ['roles' => [$request->role]],
        );

        return redirect()->back()->with('success', 'Usuario y permisos actualizados.');
    }

    /**
     * Elimina a un usuario del sistema.
     */
    public function destroy(User $usuario): RedirectResponse
    {
        if ($usuario->id === Auth::id()) {
            return redirect()->back()->with('error', 'No puedes eliminar tu propia cuenta.');
        }

        Auditoria::registrar('usuario.eliminado', 'user', $usuario->id, ['roles' => $usuario->getRoleNames()->all()], null);

        $usuario->delete();

        return redirect()->back()->with('success', 'Usuario eliminado correctamente.');
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
