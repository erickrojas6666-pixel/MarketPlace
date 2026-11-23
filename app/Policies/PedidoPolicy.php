<?php

namespace App\Policies;

use App\Models\Pedido;
use App\Models\User;

/** Quién ve y quién sube la evidencia fotográfica de un pedido. */
class PedidoPolicy
{
    /** Estados en los que cada parte puede aportar evidencia. */
    private const SUBE_PRESTADOR = [Pedido::EN_PROGRESO, Pedido::COMPLETADO];
    private const SUBE_CLIENTE   = [Pedido::COMPLETADO, Pedido::EN_DISPUTA];

    public static function esCliente(User $user, Pedido $pedido): bool
    {
        return $pedido->cliente_id === $user->id;
    }

    public static function esPrestador(User $user, Pedido $pedido): bool
    {
        $pedido->loadMissing('servicio.perfilPrestador');

        $prestadorUserId = $pedido->servicio?->perfilPrestador?->user_id;

        return $prestadorUserId !== null && $prestadorUserId === $user->id;
    }

    /**
     * Las dos partes siempre. El mediador (o superadmin) solo mientras el pedido está
     * en disputa, y solo si no es parte del pedido (mínimo privilegio, RF-49).
     */
    public function verEvidencias(User $user, Pedido $pedido): bool
    {
        if (self::esCliente($user, $pedido) || self::esPrestador($user, $pedido)) {
            return true;
        }

        return $pedido->estado === Pedido::EN_DISPUTA
            && $user->hasAnyRole(['mediador', 'superadmin']);
    }

    public function subirEvidencia(User $user, Pedido $pedido): bool
    {
        if (self::esPrestador($user, $pedido) && in_array($pedido->estado, self::SUBE_PRESTADOR, true)) {
            return true;
        }

        return self::esCliente($user, $pedido) && in_array($pedido->estado, self::SUBE_CLIENTE, true);
    }
}
