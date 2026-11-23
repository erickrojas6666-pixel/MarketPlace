<?php

namespace App\Actions\Pagos;

use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Transaccione;
use App\Services\MercadoPagoService;
use App\Support\Auditoria;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Devuelve al cliente el pago retenido (cierre del escrow cuando la disputa se resuelve a su favor).
 *
 * Con la pasarela simulada solo se registra el movimiento en el libro. Con Mercado Pago
 * se pide primero la devolución real: si la pasarela falla se aborta (502) y, como esto
 * corre dentro de la transacción de quien lo llama, nada queda a medias en la base.
 */
class ReembolsarPago
{
    public function __construct(private readonly MercadoPagoService $mercadoPago)
    {
    }

    /**
     * Núcleo del reembolso. Quien lo llame decide QUIÉN está autorizado y debe hacerlo
     * dentro de una transacción y con el pago bloqueado (lockForUpdate).
     *
     * @param  string  $claveIdempotencia  estable para este reembolso (misma clave = misma devolución)
     */
    public function aplicar(Pago $pago, Pedido $pedido, string $claveIdempotencia): void
    {
        if ($pago->estado !== 'completado') {
            throw ValidationException::withMessages([
                'pago_id' => 'Este pago no está disponible para reembolsarse.',
            ]);
        }

        if ($pedido->estado !== Pedido::CANCELADO) {
            throw ValidationException::withMessages([
                'pago_id' => 'El pago solo se reembolsa cuando el pedido está cancelado.',
            ]);
        }

        $yaMovido = Transaccione::where('pago_id', $pago->id)
            ->whereIn('tipo', ['liberacion_prestador', 'reembolso_cliente'])
            ->exists();

        if ($yaMovido) {
            throw ValidationException::withMessages([
                'pago_id' => 'Este pago ya fue liberado o reembolsado.',
            ]);
        }

        if ($pago->metodo_pago === 'mercadopago') {
            // transaccion_id se guarda como "MP-{id del pago en Mercado Pago}".
            $idMercadoPago = Str::after((string) $pago->transaccion_id, 'MP-');

            if (! $this->mercadoPago->reembolsar($idMercadoPago, $claveIdempotencia)) {
                abort(502, 'No pudimos devolver el pago con Mercado Pago. Inténtalo de nuevo en unos minutos.');
            }
        }

        Transaccione::create([
            'pago_id'   => $pago->id,
            'tipo'      => 'reembolso_cliente',
            'monto'     => $pago->monto,
            'creada_en' => now(),
        ]);

        $pago->forceFill(['estado' => 'reembolsado'])->save();

        Auditoria::registrar(
            'pago.reembolsado',
            'pago',
            $pago->id,
            ['estado' => 'retenido'],
            ['estado' => 'reembolsado', 'monto' => (float) $pago->monto, 'pasarela' => $pago->metodo_pago],
        );
    }
}
