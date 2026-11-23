<?php

namespace App\Actions\Pagos;

use App\Actions\Pedidos\CambiarEstadoPedido;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Transaccione;
use App\Models\User;
use App\Support\Auditoria;
use App\Support\Pedidos\TransicionesPedido;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * El cliente paga y la plataforma RETIENE el dinero (escrow simulado).
 *
 * Las tablas pagos/transacciones se usan tal como están en la base:
 *  - pagos.estado = 'completado' significa "cobrado y en poder de la plataforma".
 *  - El libro transacciones registra el movimiento ('cobro_cliente').
 * La pasarela real (Mercado Pago / Stripe) reemplazará solo la generación de la
 * referencia; el resto del flujo no cambia.
 */
class RetenerPago
{
    public function __construct(private readonly CambiarEstadoPedido $cambiarEstado)
    {
    }

    public function ejecutar(User $cliente, int $pedidoId): Pago
    {
        $pago = DB::transaction(function () use ($cliente, $pedidoId) {
            // Se bloquea el pedido: dos pagos simultáneos del mismo pedido se serializan.
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

            $yaPagado = Pago::where('pedido_id', $pedido->id)
                ->whereIn('estado', ['pendiente', 'completado'])
                ->exists();

            if ($yaPagado) {
                throw ValidationException::withMessages([
                    'pedido_id' => 'Este pedido ya tiene un pago registrado.',
                ]);
            }

            $pago = Pago::forceCreate([
                'pedido_id'      => $pedido->id,
                'monto'          => $monto,
                'metodo_pago'    => 'simulado',
                'estado'         => 'completado',
                'transaccion_id' => 'SIM-'.Str::upper(Str::random(16)),
            ]);

            Transaccione::create([
                'pago_id'   => $pago->id,
                'tipo'      => 'cobro_cliente',
                'monto'     => $monto,
                'creada_en' => now(),
            ]);

            Auditoria::registrar('pago.retenido', 'pago', $pago->id, null, [
                'pedido_id' => $pedido->id,
                'monto'     => $monto,
            ]);

            return $pago;
        });

        // Con el módulo de escrow activo, el pedido pasa a "pagado" (lo marca el sistema).
        // Con el módulo apagado (por defecto) el flujo del pedido no cambia.
        if (config('marketplace.escrow_habilitado')) {
            $pedido = Pedido::find($pago->pedido_id);
            $error  = $pedido
                ? $this->cambiarEstado->ejecutar($pedido, Pedido::PAGADO, [TransicionesPedido::SISTEMA], $cliente)
                : 'pedido inexistente';

            if ($error !== null) {
                Log::warning('Pago retenido pero el pedido no pasó a pagado', ['pago_id' => $pago->id]);
            }
        }

        return $pago->load('transacciones');
    }
}
