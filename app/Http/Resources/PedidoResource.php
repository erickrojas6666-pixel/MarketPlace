<?php

namespace App\Http\Resources;

use App\Actions\Pedidos\CambiarEstadoPedido;
use App\Support\Pedidos\TransicionesPedido;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Pedido visible solo para sus partes (cliente, prestador) o para mediación. */
class PedidoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $usuario = $request->user();
        $actores = $usuario ? CambiarEstadoPedido::actoresDe($usuario, $this->resource) : [];

        return [
            'id'     => $this->id,
            'estado' => $this->estado,
            // Estados a los que ESTE usuario puede mover el pedido ahora mismo.
            'acciones_permitidas' => TransicionesPedido::permitidas($this->estado, $actores),
            'servicio' => $this->whenLoaded('servicio', fn () => [
                'id'          => $this->servicio->id,
                'titulo'      => $this->servicio->titulo,
                'precio_base' => $this->servicio->precio_base !== null ? (float) $this->servicio->precio_base : null,
            ]),
            'cliente' => $this->whenLoaded('cliente', fn () => [
                'nombre' => $this->cliente?->nombre,
            ]),
            'prestador' => $this->whenLoaded('servicio', fn () => [
                'nombre' => $this->servicio->perfilPrestador?->user?->nombre,
            ]),
            'direccion_servicio' => $this->direccion_servicio,
            'fecha_programada'   => $this->fecha_programada?->toIso8601String(),
            'created_at'         => $this->created_at?->toIso8601String(),
        ];
    }
}
