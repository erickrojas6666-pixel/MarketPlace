<?php

namespace App\Actions\Pedidos;

use App\Models\Pedido;
use App\Models\User;
use App\Notifications\EstadoPedidoActualizado;
use App\Support\Auditoria;
use App\Support\Pedidos\TransicionesPedido;
use Illuminate\Support\Facades\DB;

/**
 * Único punto por el que cambia el estado de un pedido (RF-24).
 * Valida la transición, la aplica con el registro bloqueado, la audita (RF-52)
 * y avisa a las demás partes.
 */
class CambiarEstadoPedido
{
    /**
     * Roles que el usuario juega en este pedido. Sin usuario, actúa el sistema.
     *
     * El mediador solo cuenta si NO es parte del pedido: quien resuelve una disputa
     * no puede ser a la vez cliente o prestador de ese pedido.
     *
     * @return list<string>
     */
    public static function actoresDe(?User $usuario, Pedido $pedido): array
    {
        if ($usuario === null) {
            return [TransicionesPedido::SISTEMA];
        }

        $pedido->loadMissing('servicio.perfilPrestador');

        $actores = [];

        if ($pedido->cliente_id === $usuario->id) {
            $actores[] = TransicionesPedido::CLIENTE;
        }

        $prestadorUserId = $pedido->servicio?->perfilPrestador?->user_id;
        if ($prestadorUserId !== null && $prestadorUserId === $usuario->id) {
            $actores[] = TransicionesPedido::PRESTADOR;
        }

        if ($actores === [] && $usuario->hasAnyRole(['mediador', 'superadmin'])) {
            $actores[] = TransicionesPedido::MEDIADOR;
        }

        return $actores;
    }

    /**
     * ¿La transición entra o sale de "en_disputa"? Esas transiciones tienen consecuencias
     * (registro de la disputa, movimiento del dinero) y solo las aplica el flujo de disputas.
     */
    public static function involucraDisputa(?string $estadoActual, string $nuevoEstado): bool
    {
        return $estadoActual === Pedido::EN_DISPUTA || $nuevoEstado === Pedido::EN_DISPUTA;
    }

    /**
     * Cambia el estado y avisa a las demás partes.
     *
     * @param  list<string>  $actores
     * @return string|null mensaje de error, o null si el cambio se aplicó
     */
    public function ejecutar(Pedido $pedido, string $nuevoEstado, array $actores, ?User $usuario = null): ?string
    {
        $error = $this->aplicar($pedido, $nuevoEstado, $actores);

        if ($error === null) {
            $this->notificar($pedido, $nuevoEstado, $usuario);
        }

        return $error;
    }

    /**
     * Núcleo transaccional del cambio, sin notificar: así un flujo mayor (disputas) puede
     * incluirlo en SU transacción y avisar solo cuando todo quedó confirmado.
     *
     * La verificación del estado actual y el cambio van juntos y con el registro bloqueado:
     * dos clics simultáneos no pueden saltarse el flujo.
     *
     * @param  list<string>  $actores
     * @param  bool  $viaDisputa  solo los flujos de disputas lo pasan en true
     * @return string|null mensaje de error, o null si el cambio se aplicó
     */
    public function aplicar(Pedido $pedido, string $nuevoEstado, array $actores, bool $viaDisputa = false): ?string
    {
        return DB::transaction(function () use ($pedido, $nuevoEstado, $actores, $viaDisputa) {
            $estadoActual = Pedido::whereKey($pedido->id)->lockForUpdate()->value('estado');

            if (! $viaDisputa && self::involucraDisputa($estadoActual, $nuevoEstado)) {
                return 'Las disputas se abren y se resuelven desde su propio flujo, no cambiando el estado a mano.';
            }

            if (! TransicionesPedido::permite($estadoActual, $nuevoEstado, $actores)) {
                return "No puedes cambiar un pedido '{$estadoActual}' a '{$nuevoEstado}'.";
            }

            $pedido->update(['estado' => $nuevoEstado]);

            Auditoria::registrar(
                'pedido.estado_cambiado',
                'pedido',
                $pedido->id,
                ['estado' => $estadoActual],
                ['estado' => $nuevoEstado, 'actores' => $actores],
            );

            return null;
        });
    }

    /**
     * Avisa a cliente y prestador, menos a quien hizo el cambio.
     *
     * @param  string|null  $detalle  frase breve y sin datos sensibles que acompaña al aviso
     */
    public function notificar(Pedido $pedido, string $nuevoEstado, ?User $actor, ?string $detalle = null): void
    {
        $pedido->loadMissing(['cliente', 'servicio.perfilPrestador.user']);

        collect([$pedido->cliente, $pedido->servicio?->perfilPrestador?->user])
            ->filter()
            ->reject(fn (User $u) => $actor !== null && $u->id === $actor->id)
            ->unique('id')
            ->each(fn (User $u) => $u->notify(new EstadoPedidoActualizado($pedido, $nuevoEstado, $detalle)));
    }
}
