<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Pago con su estado de escrow: retenido, liberado o reembolsado.
 * (La tabla pagos solo distingue pendiente/completado/fallido/reembolsado;
 *  "liberado" se deduce del libro de transacciones.)
 */
class PagoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $movimientos = $this->relationLoaded('transacciones') ? $this->transacciones : collect();

        return [
            'id'         => $this->id,
            'pedido_id'  => $this->pedido_id,
            'monto'      => (float) $this->monto,
            'metodo'     => $this->metodo_pago,
            'referencia' => $this->transaccion_id,
            'estado'     => $this->estadoEscrow($movimientos),
            'movimientos' => $movimientos->map(fn ($t) => [
                'tipo'  => $t->tipo,
                'monto' => (float) $t->monto,
                'fecha' => $t->creada_en?->toIso8601String(),
            ])->values(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function estadoEscrow($movimientos): string
    {
        if ($this->estado === 'reembolsado') {
            return 'reembolsado';
        }

        if ($this->estado === 'completado') {
            return $movimientos->contains('tipo', 'liberacion_prestador') ? 'liberado' : 'retenido';
        }

        return (string) $this->estado; // pendiente | fallido
    }
}
