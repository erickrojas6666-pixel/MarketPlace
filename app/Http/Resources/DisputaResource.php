<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Disputa tal como la ve quien la abrió (API). Sin datos del mediador. */
class DisputaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'pedido_id'        => $this->pedido_id,
            'estado'           => $this->estado,
            'motivo'           => $this->motivo,
            'resolucion_notas' => $this->resolucion_notas,
            'created_at'       => $this->created_at?->toIso8601String(),
        ];
    }
}
