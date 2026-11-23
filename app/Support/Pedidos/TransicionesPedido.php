<?php

namespace App\Support\Pedidos;

use App\Models\Pedido;

/**
 * Máquina de estados del pedido (RF-24): quién puede llevarlo de un estado a otro.
 *
 * Es lógica pura (sin base de datos) para poder probarla fácilmente.
 * Los tramos que dependen de un módulo aún no construido se activan con
 * config/marketplace.php.
 */
final class TransicionesPedido
{
    public const CLIENTE   = 'cliente';
    public const PRESTADOR = 'prestador';
    public const MEDIADOR  = 'mediador';
    public const SISTEMA   = 'sistema'; // webhooks de pago y tareas programadas

    /**
     * estado actual => [estado nuevo => [actores que pueden hacerlo]]
     *
     * @return array<string, array<string, list<string>>>
     */
    public static function mapa(): array
    {
        $mapa = [
            Pedido::PENDIENTE => [
                Pedido::RECHAZADO => [self::PRESTADOR],
                Pedido::CANCELADO => [self::CLIENTE],
            ],
            Pedido::COTIZADO => [
                Pedido::ACEPTADO  => [self::CLIENTE],
                Pedido::RECHAZADO => [self::CLIENTE],
                Pedido::CANCELADO => [self::CLIENTE, self::PRESTADOR],
            ],
            Pedido::ACEPTADO => [
                Pedido::CANCELADO => [self::PRESTADOR],
            ],
            Pedido::PAGADO => [
                Pedido::EN_PROGRESO => [self::PRESTADOR],
                Pedido::CANCELADO   => [self::MEDIADOR], // reembolso: lo aplica Finanzas (RF-50)
            ],
            Pedido::EN_PROGRESO => [
                Pedido::COMPLETADO => [self::PRESTADOR],
                Pedido::CANCELADO  => [self::PRESTADOR],
            ],
            Pedido::COMPLETADO => [
                // RF-26: el cliente confirma, o el sistema lo hace al vencer el plazo.
                Pedido::CONFIRMADO => [self::CLIENTE, self::SISTEMA],
            ],
            Pedido::EN_DISPUTA => [
                Pedido::CONFIRMADO => [self::MEDIADOR], // liberar el pago
                Pedido::CANCELADO  => [self::MEDIADOR], // reembolsar
            ],
        ];

        // Sin módulo de cotizaciones el prestador acepta directo; con él, cotiza.
        if (config('marketplace.cotizaciones_habilitadas')) {
            $mapa[Pedido::PENDIENTE][Pedido::COTIZADO] = [self::PRESTADOR];
        } else {
            $mapa[Pedido::PENDIENTE][Pedido::ACEPTADO] = [self::PRESTADOR];
        }

        // Sin escrow no hay pago: aceptado → en_progreso. Con escrow, "pagado" lo
        // marca el sistema al verificar el webhook de la pasarela.
        if (config('marketplace.escrow_habilitado')) {
            $mapa[Pedido::ACEPTADO][Pedido::PAGADO] = [self::SISTEMA];
        } else {
            $mapa[Pedido::ACEPTADO][Pedido::EN_PROGRESO] = [self::PRESTADOR];
        }

        // RF-38: el cliente abre la disputa antes de confirmar.
        if (config('marketplace.disputas_habilitadas')) {
            $mapa[Pedido::COMPLETADO][Pedido::EN_DISPUTA] = [self::CLIENTE];
        }

        return $mapa;
    }

    /**
     * Estados a los que puede pasar el pedido para esos actores.
     *
     * @param  list<string>  $actores
     * @return list<string>
     */
    public static function permitidas(string $estadoActual, array $actores): array
    {
        $destinos = [];

        foreach (self::mapa()[$estadoActual] ?? [] as $nuevo => $quienes) {
            if (array_intersect($quienes, $actores) !== []) {
                $destinos[] = $nuevo;
            }
        }

        return $destinos;
    }

    /** @param list<string> $actores */
    public static function permite(string $estadoActual, string $nuevoEstado, array $actores): bool
    {
        return in_array($nuevoEstado, self::permitidas($estadoActual, $actores), true);
    }
}
