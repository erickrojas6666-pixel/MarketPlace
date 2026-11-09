<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServicioResource;
use App\Support\Catalogo\ConsultaCatalogo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Servicio 2 — Catálogo: servicios con sus paquetes (niveles). Público, solo lectura.
 */
class ServicioApiController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'categoria_id' => ['nullable', 'integer', 'min:1'],
            'q'            => ['nullable', 'string', 'max:100'],
            'per_page'     => ['nullable', 'integer', 'between:1,50'],
        ]);

        $consulta = ConsultaCatalogo::filtrar(
            ConsultaCatalogo::base(),
            isset($filtros['categoria_id']) ? (int) $filtros['categoria_id'] : null,
            $filtros['q'] ?? null,
        );

        return ServicioResource::collection(
            $consulta->latest()->paginate((int) ($filtros['per_page'] ?? 12))->withQueryString()
        );
    }

    public function show(int $servicio): ServicioResource
    {
        // Mismas reglas de visibilidad que el listado: inactivo o sin verificar = 404.
        return new ServicioResource(ConsultaCatalogo::base()->findOrFail($servicio));
    }
}
