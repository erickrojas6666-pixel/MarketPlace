<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PerfilPrestador;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Throwable;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        // Fuera del try: ValidationException debe propagarse a Inertia
        $datos = $request->validate([
            'nombre'       => ['required', 'string', 'max:255'],
            'email'        => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password'     => ['required', 'confirmed', Rules\Password::defaults()],
            'tipo_usuario' => ['required', 'in:cliente,prestador'],
        ]);

        try {
            $user = DB::transaction(function () use ($datos) {
                $user = User::create([
                    'nombre'   => $datos['nombre'],
                    'email'    => $datos['email'],
                    'password' => Hash::make($datos['password']),
                ]);

                $user->assignRole($datos['tipo_usuario']);

                if ($datos['tipo_usuario'] === 'prestador') {
                    PerfilPrestador::create([
                        'user_id'             => $user->id,
                        'estado_verificacion' => 'pendiente',
                    ]);
                }

                return $user;
            });
        } catch (RoleDoesNotExist $e) {
            // Falta correr el seeder de roles
            Log::critical('Registro: el rol no existe. ¿Se ejecutó RoleSeeder?', [
                'tipo_usuario' => $datos['tipo_usuario'],
                'error'        => $e->getMessage(),
            ]);

            return $this->errorRegistro($request, 'No pudimos completar tu registro. Intenta más tarde.');
        } catch (QueryException $e) {
            // Por ejemplo, correo duplicado por condición de carrera (UNIQUE en BD)
            $duplicado = ($e->errorInfo[1] ?? null) === 1062;

            Log::error('Registro: error de base de datos.', [
                'codigo' => $e->errorInfo[1] ?? null,
                'error'  => $e->getMessage(),
            ]);

            return $this->errorRegistro(
                $request,
                $duplicado
                    ? 'Ese correo ya está registrado.'
                    : 'Ocurrió un problema al guardar tus datos. Intenta de nuevo.'
            );
        } catch (Throwable $e) {
            Log::error('Registro: error inesperado.', [
                'clase' => $e::class,
                'error' => $e->getMessage(),
            ]);

            return $this->errorRegistro($request, 'Ocurrió un error inesperado. Intenta de nuevo.');
        }

        // El correo de verificación no debe deshacer un registro ya exitoso
        try {
            event(new Registered($user));
        } catch (Throwable $e) {
            Log::warning('Registro: no se pudo disparar Registered/enviar correo.', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }

    private function errorRegistro(Request $request, string $mensaje): RedirectResponse
    {
        return back()
            ->withInput($request->except('password', 'password_confirmation'))
            ->withErrors(['general' => $mensaje]);
    }
}