<?php

namespace App\Actions\Pagos;

use App\Models\Pago;
use App\Models\Pedido;
use App\Models\User;
use App\Services\MercadoPagoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Paso 1 del pago real: crea el pago en estado "pendiente" y la pantalla de cobro de
 * Mercado Pago. El dinero NO se considera retenido hasta que Mercado Pago confirme
 * (ver ConfirmarPagoMercadoPago).
 */
class IniciarPagoMercadoPago
{
    public function __construct(private readonly MercadoPagoService $mercadoPago)
    {
    }

    /**
     * @return array{0: Pago, 1: string} El pago y la URL a la que se envía al cliente.
     */
    public function ejecutar(User $cliente, int $pedidoId): array
    {
        if (! $this->mercadoPago->configurado()) {
            abort(503, 'La pasarela de pago no está disponible por ahora.');
        }

        [$pago, $esNuevo, $titulo, $servicioId, $monto] = DB::transaction(function () use ($cliente, $pedidoId) {
            // Se bloquea el pedido: dos clics simultáneos en "Pagar" no crean dos pagos.
            $pedido = Pedido::with('servicio')->whereKey($pedidoId)->lockForUpdate()->first();

            // Si no es suyo, se responde igual que si no existiera.
            abort_if($pedido === null || (int) $pedido->cliente_id !== (int) $cliente->id, 404);

            if ($pedido->estado !== Pedido::ACEPTADO) {
                throw ValidationException::withMessages([
                    'pedido_id' => "Solo se puede pagar un pedido aceptado (estado actual: {$pedido->estado}).",
                ]);
            }

            $monto = round((float) ($pedido->servicio?->precio_base ?? 0), 2);

            if ($monto <= 0) {
                throw ValidationException::withMessages([
                    'pedido_id' => 'El servicio no tiene un precio definido.',
                ]);
            }

            if (Pago::where('pedido_id', $pedido->id)->where('estado', 'completado')->exists()) {
                throw ValidationException::withMessages([
                    'pedido_id' => 'Este pedido ya tiene un pago registrado.',
                ]);
            }

            // Un pago pendiente (checkout abandonado) se reutiliza en vez de crear otro.
            $pago    = Pago::where('pedido_id', $pedido->id)->where('estado', 'pendiente')->lockForUpdate()->first();
            $esNuevo = $pago === null;

            if ($esNuevo) {
                $pago = Pago::forceCreate([
                    'pedido_id'      => $pedido->id,
                    'monto'          => $monto,
                    'metodo_pago'    => 'mercadopago',
                    'estado'         => 'pendiente',
                    'transaccion_id' => null,
                ]);
            }

            return [$pago, $esNuevo, (string) $pedido->servicio->titulo, (int) $pedido->servicio_id, $monto];
        });

        $retorno = route('pagos.retorno');

        $cuerpo = [
            'items' => [[
                'id'          => (string) $servicioId,
                'title'       => Str::limit($titulo, 120, ''),
                'quantity'    => 1,
                'currency_id' => 'MXN',
                'unit_price'  => $monto,
            ]],
            // Liga el pago de Mercado Pago con nuestro registro.
            'external_reference'   => (string) $pago->id,
            'back_urls'            => ['success' => $retorno, 'pending' => $retorno, 'failure' => $retorno],
            'statement_descriptor' => 'OFICIOSYA',
        ];

        // auto_return exige https; en local el cliente usa el botón "Volver al sitio".
        if (str_starts_with($retorno, 'https://')) {
            $cuerpo['auto_return'] = 'approved';
        }

        // La URL de notificaciones debe ser pública (https); en local suele omitirse.
        if (filled(config('services.mercadopago.notification_url'))) {
            $cuerpo['notification_url'] = config('services.mercadopago.notification_url');
        }

        $preferencia = $this->mercadoPago->crearPreferencia($cuerpo);

        if ($preferencia === null) {
            if ($esNuevo) {
                $pago->delete(); // no dejar un pago pendiente sin pantalla de cobro
            }

            abort(502, 'No pudimos iniciar el pago con Mercado Pago. Inténtalo de nuevo.');
        }

        $pago->forceFill(['transaccion_id' => 'PREF-'.$preferencia['id']])->save();

        $url = $this->mercadoPago->sandbox() && filled($preferencia['sandbox_init_point'])
            ? $preferencia['sandbox_init_point']
            : $preferencia['init_point'];

        return [$pago->load('transacciones'), $url];
    }
}
