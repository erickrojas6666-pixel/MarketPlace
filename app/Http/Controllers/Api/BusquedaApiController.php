<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServicioResource;
use App\Support\Catalogo\ConsultaCatalogo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Servicio 3 — Búsqueda por cercanía: solo prestadores que atienden el punto indicado,
 * del más cercano al más lejano. La ubicación del cliente viaja en la petición y no se guarda.
 */
class BusquedaApiController extends Controller
{
    private const RADIO_POR_DEFECTO_KM = 10;

    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'lat'          => ['required', 'numeric', 'between:-90,90'],
            'lng'          => ['required', 'numeric', 'between:-180,180'],
            'radio_km'     => ['nullable', 'integer', 'between:1,50'],
            'categoria_id' => ['nullable', 'integer', 'min:1'],
            'q'            => ['nullable', 'string', 'max:100'],
            'per_page'     => ['nullable', 'integer', 'between:1,50'],
        ]);

        $consulta = ConsultaCatalogo::filtrar(
            ConsultaCatalogo::base(),
            isset($filtros['categoria_id']) ? (int) $filtros['categoria_id'] : null,
            $filtros['q'] ?? null,
        )->cercanosA(
            (float) $filtros['lat'],
            (float) $filtros['lng'],
            (float) ($filtros['radio_km'] ?? self::RADIO_POR_DEFECTO_KM),
        );

        return ServicioResource::collection(
            $consulta->paginate((int) ($filtros['per_page'] ?? 12))->withQueryString()
        );
    }
}
