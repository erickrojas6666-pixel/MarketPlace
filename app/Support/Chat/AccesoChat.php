<?php

namespace App\Support\Chat;

use App\Models\Pedido;
use App\Models\User;
use App\Policies\PedidoPolicy;

/**
 * Quién participa y quién puede escribir en el chat de un pedido (RF-22).
 *
 * Solo el cliente y el prestador del pedido. Con el trabajo confirmado o el pedido
 * cancelado o rechazado la conversación queda de solo lectura.
 */
final class AccesoChat
{
    /** Estados en los que todavía se puede escribir. */
    public const ESCRIBIBLES = [
        Pedido::PENDIENTE,
        Pedido::COTIZADO,
        Pedido::ACEPTADO,
        Pedido::PAGADO,
        Pedido::EN_PROGRESO,
        Pedido::COMPLETADO,
        Pedido::EN_DISPUTA,
    ];

    public static function esParte(User $user, Pedido $pedido): bool
    {
        return PedidoPolicy::esCliente($user, $pedido) || PedidoPolicy::esPrestador($user, $pedido);
    }

    public static function puedeEscribir(User $user, Pedido $pedido): bool
    {
        return self::esParte($user, $pedido) && in_array($pedido->estado, self::ESCRIBIBLES, true);
    }

    /** La otra persona de la conversación. */
    public static function contraparte(User $user, Pedido $pedido): ?User
    {
        $pedido->loadMissing(['cliente', 'servicio.perfilPrestador.user']);

        return PedidoPolicy::esCliente($user, $pedido)
            ? $pedido->servicio?->perfilPrestador?->user
            : $pedido->cliente;
    }
}
