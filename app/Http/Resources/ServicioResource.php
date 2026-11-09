<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Servicio público: sin correo ni coordenadas del prestador. */
class ServicioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $promedio  = $this->resource->getAttribute('calificacion_promedio');
        $distancia = $this->resource->getAttribute('distancia_km');

        return [
            'id'          => $this->id,
            'titulo'      => $this->titulo,
            'descripcion' => $this->descripcion,
            'precio_base' => $this->precio_base !== null ? (float) $this->precio_base : null,
            'categoria'   => $this->whenLoaded('categoria', fn () => [
                'id'     => $this->categoria->id,
                'nombre' => $this->categoria->nombre,
            ]),
            'prestador' => [
                'nombre' => $this->perfilPrestador?->user?->nombre,
            ],
            'calificacion_promedio' => $promedio !== null ? round((float) $promedio, 1) : null,
            'resenas_count'         => (int) ($this->resource->getAttribute('resenas_count') ?? 0),
            'niveles'               => $this->whenLoaded('niveles', fn () => $this->niveles->map(fn ($n) => [
                'nivel'        => $n->nivel,
                'precio'       => (float) $n->precio,
                'duracion_min' => (int) $n->duracion_min,
                'detalles'     => $n->detalles,
            ])->values()),
            'distancia_km' => $this->when($distancia !== null, fn () => (float) $distancia),
        ];
    }
}
