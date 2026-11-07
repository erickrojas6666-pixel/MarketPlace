<?php

namespace App\Support;

use App\Models\LogAuditorium;
use Illuminate\Support\Facades\Auth;

/**
 * Registro de auditoría de solo inserción (RF-52, RNF Auditoría).
 *
 * Reglas:
 *  - Nunca pasar contraseñas, tokens, datos de tarjeta ni documentos en $antes/$despues.
 *  - Llamar DENTRO de la transacción del cambio auditado: si no se puede registrar,
 *    el cambio se revierte (no existen acciones sin rastro).
 */
final class Auditoria
{
    /**
     * @param  string  $accion  p. ej. 'pedido.estado_cambiado', 'prestador.verificacion_cambiada'
     * @param  string  $entidad  p. ej. 'pedido', 'perfil_prestador', 'user'
     * @param  array<string, mixed>|null  $antes
     * @param  array<string, mixed>|null  $despues
     */
    public static function registrar(
        string $accion,
        string $entidad,
        int $entidadId,
        ?array $antes = null,
        ?array $despues = null,
    ): LogAuditorium {
        return LogAuditorium::create([
            'actor_id'      => Auth::id(), // null cuando actúa el sistema (scheduler, webhooks)
            'accion'        => $accion,
            'entidad'       => $entidad,
            'entidad_id'    => $entidadId,
            'datos_antes'   => $antes,
            'datos_despues' => $despues,
            'ip_origen'     => app()->runningInConsole() ? 'cli' : (request()->ip() ?? 'desconocida'),
            'creada_en'     => now(),
        ]);
    }
}
