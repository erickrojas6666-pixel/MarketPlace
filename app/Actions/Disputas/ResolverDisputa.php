<?php

namespace App\Actions\Disputas;

use App\Actions\Pagos\LiberarPago;
use App\Actions\Pagos\ReembolsarPago;
use App\Actions\Pedidos\CambiarEstadoPedido;
use App\Models\Disputa;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\User;
use App\Support\Auditoria;
use App\Support\Pedidos\TransicionesPedido;
use Illuminate\Support\Facades\DB;

/**
 * El mediador decide la disputa y se cierra el escrow (RF-38):
 *
 *  - favor_prestador → pedido "confirmado" y se libera el pago (neto + comisión).
 *  - favor_cliente   → pedido "cancelado" y se reembolsa el pago completo, sin comisión.
 *
 * Todo ocurre en UNA transacción con la disputa y el pago bloqueados. El movimiento del
 * dinero va al final: si la pasarela falla (Mercado Pago), la excepción deshace también el
 * cambio de pedido y de disputa, y el mediador puede reintentar sin que nada quede a medias.
 * Quien llama debe haber comprobado ya la autorización (DisputaPolicy::mediar).
 */
class ResolverDisputa
{
    public function __construct(
        private readonly CambiarEstadoPedido $cambiarEstado,
        private readonly LiberarPago $liberarPago,
        private readonly ReembolsarPago $reembolsarPago,
    ) {
    }

    /**
     * @param  string  $decision  Disputa::FAVOR_CLIENTE o Disputa::FAVOR_PRESTADOR
     *
     * @throws DisputaException                     caso ya resuelto o tomado por otro mediador
     * @throws \Illuminate\Validation\ValidationException  el pago no se puede liberar o reembolsar
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException  502 si la pasarela rechaza la devolución
     */
    public function ejecutar(User $mediador, int $disputaId, string $decision, string $notas): Disputa
    {
        $aFavorDelCliente = $decision === Disputa::FAVOR_CLIENTE;
        $estadoPedido     = $aFavorDelCliente ? Pedido::CANCELADO : Pedido::CONFIRMADO;
        $estadoDisputa    = $aFavorDelCliente ? Disputa::RESUELTA_FAVOR_CLIENTE : Disputa::RESUELTA_FAVOR_PRESTADOR;

        $resultado = DB::transaction(function () use ($mediador, $disputaId, $decision, $notas, $aFavorDelCliente, $estadoPedido, $estadoDisputa) {
            $disputa = Disputa::whereKey($disputaId)->lockForUpdate()->firstOrFail();

            if (! $disputa->estaAbierta()) {
                throw new DisputaException('Esta disputa ya fue resuelta.');
            }

            if (! $disputa->puedeSerGestionadaPor($mediador)) {
                throw new DisputaException('Otro mediador ya tomó este caso.');
            }

            $pedido  = Pedido::with('servicio.perfilPrestador')->findOrFail($disputa->pedido_id);
            $actores = CambiarEstadoPedido::actoresDe($mediador, $pedido);

            // Defensa en profundidad: quien es parte del pedido no actúa como mediador.
            abort_unless(in_array(TransicionesPedido::MEDIADOR, $actores, true), 403);

            $error = $this->cambiarEstado->aplicar($pedido, $estadoPedido, $actores, viaDisputa: true);

            if ($error !== null) {
                throw new DisputaException($error);
            }

            $antes = ['estado' => $disputa->estado, 'mediador_id' => $disputa->mediador_id];

            $disputa->update([
                'estado'           => $estadoDisputa,
                'mediador_id'      => $mediador->id,
                'resolucion_notas' => $notas,
            ]);

            // Solo la decisión y el actor: el texto de las notas no se copia a la auditoría.
            Auditoria::registrar('disputa.resuelta', 'disputa', $disputa->id, $antes, [
                'estado'      => $estadoDisputa,
                'decision'    => $decision,
                'mediador_id' => $mediador->id,
            ]);

            // El dinero, al final. Sin pago cobrado (o ya movido) solo se cierra el pedido.
            $pago = Pago::where('pedido_id', $pedido->id)->lockForUpdate()->first();

            if ($pago !== null && $pago->estado === 'completado') {
                if ($aFavorDelCliente) {
                    // Clave estable: si hay que reintentar, Mercado Pago reconoce la misma devolución.
                    $this->reembolsarPago->aplicar($pago, $pedido, "disputa-{$disputa->id}-pago-{$pago->id}");
                } else {
                    $this->liberarPago->aplicar($pago, $pedido);
                }
            }

            return ['disputa' => $disputa, 'pedido' => $pedido];
        });

        // Ya confirmado todo: se avisa a las dos partes (el mediador no es parte, así que no se excluye a nadie).
        $this->cambiarEstado->notificar(
            $resultado['pedido'],
            $estadoPedido,
            $mediador,
            $aFavorDelCliente
                ? 'La disputa se resolvió a favor del cliente.'
                : 'La disputa se resolvió a favor del prestador.',
        );

        return $resultado['disputa'];
    }
}
