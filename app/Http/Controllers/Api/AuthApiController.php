<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Servicio 1 — Autenticación: emite y revoca tokens Bearer (Laravel Sanctum).
 */
class AuthApiController extends Controller
{
    private const MAX_INTENTOS = 5;
    private const MAX_TOKENS_ACTIVOS = 5;

    public function login(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'email'       => ['required', 'string', 'email', 'max:255'],
            'password'    => ['required', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:60'],
        ]);

        // Límite por correo + IP: frena la fuerza bruta aunque se cambie de dirección IP.
        $llave = Str::transliterate(Str::lower($datos['email']).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($llave, self::MAX_INTENTOS)) {
            $segundos = RateLimiter::availableIn($llave);

            return response()
                ->json(['message' => "Demasiados intentos. Intenta de nuevo en {$segundos} segundos."], 429)
                ->header('Retry-After', (string) $segundos);
        }

        $usuario = User::where('email', $datos['email'])->first();

        // Siempre se hace una comparación de hash: así el tiempo de respuesta no revela
        // si el correo existe (enumeración de usuarios).
        $hash   = $usuario?->password ?? Hash::make(Str::random(16));
        $valido = Hash::check($datos['password'], $hash) && $usuario !== null;

        if (! $valido) {
            RateLimiter::hit($llave, 60);

            // Mismo mensaje exista o no el correo.
            return response()->json(['message' => 'Credenciales incorrectas.'], 401);
        }

        RateLimiter::clear($llave);

        // Se conservan como máximo N sesiones de API por usuario (las más recientes).
        $sobrantes = $usuario->tokens()
            ->orderByDesc('id')
            ->skip(self::MAX_TOKENS_ACTIVOS - 1)
            ->take(100)
            ->pluck('id');

        if ($sobrantes->isNotEmpty()) {
            $usuario->tokens()->whereIn('id', $sobrantes)->delete();
        }

        $minutos = (int) config('sanctum.expiration');
        $expira  = $minutos > 0 ? now()->addMinutes($minutos) : null;

        $token = $usuario->createToken($datos['device_name'] ?? 'api', ['*'], $expira);

        return response()->json([
            'token'      => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expira?->toIso8601String(),
            'usuario'    => $this->datosDe($usuario),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['usuario' => $this->datosDe($request->user())]);
    }

    /** Revoca solo el token con el que se hizo la petición. */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }

        return response()->json(['message' => 'Sesión cerrada.']);
    }

    private function datosDe(User $usuario): array
    {
        return [
            'id'     => $usuario->id,
            'nombre' => $usuario->nombre,
            'email'  => $usuario->email,
            'roles'  => $usuario->getRoleNames()->values(),
        ];
    }
}
