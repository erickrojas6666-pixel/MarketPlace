<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Reseña pública: solo el primer nombre del autor, sin ids de usuario ni de pedido. */
class ResenaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $nombre = trim((string) $this->cliente?->nombre);

        return [
            'id'           => $this->id,
            'calificacion' => (int) $this->calificacion,
            'comentario'   => $this->comentario,
            'autor'        => $nombre !== '' ? explode(' ', $nombre)[0] : 'Cliente',
            'fecha'        => $this->created_at?->toIso8601String(),
        ];
    }
}
