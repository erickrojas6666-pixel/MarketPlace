<?php

namespace App\Actions\Pagos;

use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Transaccione;
use App\Models\User;
use App\Support\Auditoria;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Libera el pago retenido: el prestador recibe el monto menos la comisión de la plataforma.
 * Solo procede cuando el pedido está confirmado, y una sola vez.
 */
class LiberarPago
{
    public function ejecutar(User $actor, int $pagoId): Pago
    {
        DB::transaction(function () use ($actor, $pagoId) {
            // Bloqueo del pago: dos liberaciones simultáneas no pueden pagar dos veces.
            $pago   = Pago::whereKey($pagoId)->lockForUpdate()->first();
            $pedido = $pago ? Pedido::find($pago->pedido_id) : null;

            abort_if($pago === null || $pedido === null, 404);

            $esCliente = (int) $pedido->cliente_id === (int) $actor->id;

            // Quien no es parte (ni administrador) no debe saber que el pago existe.
            abort_unless($esCliente || $actor->hasAnyRole(['admin', 'superadmin']), 404);

            if ($pago->estado !== 'completado') {
                throw ValidationException::withMessages([
                    'pago_id' => 'Este pago no está disponible para liberarse.',
                ]);
            }

            if ($pedido->estado !== Pedido::CONFIRMADO) {
                throw ValidationException::withMessages([
                    'pago_id' => 'El pago solo se libera cuando el trabajo está confirmado.',
                ]);
            }

            $yaLiberado = Transaccione::where('pago_id', $pago->id)
                ->where('tipo', 'liberacion_prestador')
                ->exists();

            if ($yaLiberado) {
                throw ValidationException::withMessages([
                    'pago_id' => 'Este pago ya fue liberado.',
                ]);
            }

            $monto    = (float) $pago->monto;
            $pct      = max(0.0, min(100.0, (float) config('marketplace.comision_porcentaje')));
            $comision = round($monto * $pct / 100, 2);
            $neto     = round($monto - $comision, 2);

            Transaccione::create([
                'pago_id'   => $pago->id,
                'tipo'      => 'liberacion_prestador',
                'monto'     => $neto,
                'creada_en' => now(),
            ]);

            if ($comision > 0) {
                Transaccione::create([
                    'pago_id'   => $pago->id,
                    'tipo'      => 'comision_plataforma',
                    'monto'     => $comision,
                    'creada_en' => now(),
                ]);
            }

            Auditoria::registrar(
                'pago.liberado',
                'pago',
                $pago->id,
                ['estado' => 'retenido'],
                ['estado' => 'liberado', 'comision' => $comision, 'neto' => $neto],
            );
        });

        return Pago::with('transacciones')->findOrFail($pagoId);
    }
}
