<?php

namespace App\Actions\Pagos;

use App\Actions\Pedidos\CambiarEstadoPedido;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Transaccione;
use App\Services\MercadoPagoService;
use App\Support\Auditoria;
use App\Support\Pedidos\TransicionesPedido;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Paso 2 del pago real: confirma con Mercado Pago (no con lo que diga el navegador ni la
 * notificación) si el pago fue aprobado y, solo entonces, lo marca como retenido.
 *
 * Es seguro llamarla varias veces para el mismo pago (webhook + regreso del cliente):
 * el pago se bloquea y solo se confirma una vez.
 */
class ConfirmarPagoMercadoPago
{
    public function __construct(
        private readonly MercadoPagoService $mercadoPago,
        private readonly CambiarEstadoPedido $cambiarEstado,
    ) {
    }

    /**
     * @return array{pago: Pago, resultado: string}|null  resultado: aprobado | ya_confirmado | pendiente | rechazado | inconsistente
     */
    public function ejecutar(string $paymentId): ?array
    {
        $datos = $this->mercadoPago->obtenerPago($paymentId);

        if ($datos === null) {
            return null;
        }

        $pagoId    = (int) ($datos['external_reference'] ?? 0);
        $estadoMp  = (string) ($datos['status'] ?? '');
        $resultado = 'pendiente';

        if ($pagoId <= 0) {
            return null; // no es un pago de OficiosYa
        }

        $pago = DB::transaction(function () use ($pagoId, $paymentId, $datos, $estadoMp, &$resultado) {
            $pago = Pago::whereKey($pagoId)->lockForUpdate()->first();

            if ($pago === null || $pago->metodo_pago !== 'mercadopago') {
                return null;
            }

            if ($pago->estado === 'completado') {
                $resultado = 'ya_confirmado';

                return $pago;
            }

            if ($estadoMp === 'approved') {
                // El monto y la moneda cobrados deben coincidir con lo que pedimos.
                $montoOk  = abs((float) ($datos['transaction_amount'] ?? 0) - (float) $pago->monto) < 0.01;
                $monedaOk = ($datos['currency_id'] ?? 'MXN') === 'MXN';

                if (! $montoOk || ! $monedaOk) {
                    Log::warning('Pago de Mercado Pago con monto o moneda distintos', ['pago_id' => $pago->id]);
                    $resultado = 'inconsistente';

                    return $pago;
                }

                $pago->forceFill(['estado' => 'completado', 'transaccion_id' => 'MP-'.$paymentId])->save();

                Transaccione::create([
                    'pago_id'   => $pago->id,
                    'tipo'      => 'cobro_cliente',
                    'monto'     => $pago->monto,
                    'creada_en' => now(),
                ]);

                Auditoria::registrar('pago.retenido', 'pago', $pago->id, null, [
                    'pedido_id' => $pago->pedido_id,
                    'monto'     => (float) $pago->monto,
                    'pasarela'  => 'mercadopago',
                ]);

                $resultado = 'aprobado';
            } elseif (in_array($estadoMp, ['rejected', 'cancelled'], true)) {
                // 'fallido' permite que el cliente genere un pago nuevo.
                $pago->forceFill(['estado' => 'fallido'])->save();
                $resultado = 'rechazado';
            }

            return $pago;
        });

        if ($pago === null) {
            return null;
        }

        // Con el módulo de escrow activo, el pedido pasa a "pagado" (lo marca el sistema).
        if ($resultado === 'aprobado' && config('marketplace.escrow_habilitado')) {
            $pedido = Pedido::find($pago->pedido_id);
            $error  = $pedido
                ? $this->cambiarEstado->ejecutar($pedido, Pedido::PAGADO, [TransicionesPedido::SISTEMA])
                : 'pedido inexistente';

            if ($error !== null) {
                Log::warning('Pago retenido pero el pedido no pasó a pagado', ['pago_id' => $pago->id]);
            }
        }

        return ['pago' => $pago->load('transacciones'), 'resultado' => $resultado];
    }
}
