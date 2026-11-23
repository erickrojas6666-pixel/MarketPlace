<?php

namespace App\Support;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Evita que reintentar una petición (p. ej. la cola offline del cliente) repita
 * su efecto. El cliente manda un UUID en la cabecera "Idempotency-Key".
 *
 * Si la clave ya se procesó (por el mismo usuario y ámbito), se devuelve el
 * resultado guardado con 'duplicado' => true en lugar de ejecutar la acción otra vez.
 * Un candado impide que dos envíos simultáneos con la misma clave corran a la vez.
 */
final class Idempotencia
{
    private const VIGENCIA_HORAS = 24;

    public static function clave(Request $request): ?string
    {
        $clave = $request->header('Idempotency-Key');

        return is_string($clave) && preg_match('/^[A-Za-z0-9\-]{16,64}$/', $clave) ? $clave : null;
    }

    /**
     * @param  Closure():array<string, mixed>  $accion  Debe devolver un arreglo serializable.
     * @return array<string, mixed>
     */
    public static function ejecutar(Request $request, string $ambito, Closure $accion): array
    {
        $clave = self::clave($request);

        if ($clave === null) {
            return $accion();
        }

        $llave = "idem:{$ambito}:{$request->user()?->id}:{$clave}";

        return Cache::lock("{$llave}:lock", 10)->block(5, function () use ($llave, $accion) {
            $previo = Cache::get($llave);

            if (is_array($previo)) {
                return $previo + ['duplicado' => true];
            }

            $resultado = $accion();
            Cache::put($llave, $resultado, now()->addHours(self::VIGENCIA_HORAS));

            return $resultado;
        });
    }
}
