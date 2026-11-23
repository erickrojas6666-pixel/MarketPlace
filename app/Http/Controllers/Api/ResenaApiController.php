<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ResenaResource;
use App\Models\Pedido;
use App\Models\Resena;
use App\Support\Catalogo\ConsultaCatalogo;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

/**
 * Servicio 6 — Reseñas: el cliente califica un trabajo confirmado; las reseñas de un
 * servicio son públicas. (La calificación del prestador al cliente llega en la Fase 2.)
 */
class ResenaApiController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'servicio_id' => ['required', 'integer', 'min:1'],
            'per_page'    => ['nullable', 'integer', 'between:1,50'],
        ]);

        // Solo de servicios públicos (activos y de prestadores verificados).
        abort_unless(ConsultaCatalogo::base()->whereKey($filtros['servicio_id'])->exists(), 404);

        $resenas = Resena::query()
            ->with('cliente:id,nombre')
            ->where('servicio_id', $filtros['servicio_id'])
            ->latest('id')
            ->paginate((int) ($filtros['per_page'] ?? 12))
            ->withQueryString();

        return ResenaResource::collection($resenas);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'pedido_id'    => ['required', 'integer', 'exists:pedidos,id'],
            'calificacion' => ['required', 'integer', 'min:1', 'max:5'],
            'comentario'   => ['nullable', 'string', 'max:1000'],
        ]);

        $usuario = $request->user();
        $pedido  = Pedido::findOrFail($datos['pedido_id']);

        // Solo el cliente dueño del pedido puede reseñar (a los demás: 404).
        abort_if((int) $pedido->cliente_id !== (int) $usuario->id, 404);

        // RF-35: solo se califica un trabajo ya confirmado.
        if ($pedido->estado !== Pedido::CONFIRMADO) {
            throw ValidationException::withMessages([
                'pedido_id' => 'Solo puedes calificar pedidos confirmados.',
            ]);
        }

        if (Resena::where('pedido_id', $pedido->id)->exists()) {
            throw ValidationException::withMessages([
                'pedido_id' => 'Este pedido ya fue calificado.',
            ]);
        }

        try {
            $resena = Resena::create([
                'pedido_id'    => $pedido->id,
                'cliente_id'   => $usuario->id,
                'servicio_id'  => $pedido->servicio_id,
                'calificacion' => $datos['calificacion'],
                'comentario'   => $datos['comentario'] ?? null,
            ]);
        } catch (QueryException $e) {
            // 1062 = entrada duplicada: dos envíos simultáneos.
            if (($e->errorInfo[1] ?? null) === 1062) {
                throw ValidationException::withMessages([
                    'pedido_id' => 'Este pedido ya fue calificado.',
                ]);
            }

            throw $e;
        }

        return (new ResenaResource($resena->load('cliente:id,nombre')))
            ->response()
            ->setStatusCode(201);
    }
}
