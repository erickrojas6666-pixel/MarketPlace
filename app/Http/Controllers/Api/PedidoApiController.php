<?php

namespace App\Http\Controllers\Api;

use App\Actions\Pedidos\CambiarEstadoPedido;
use App\Actions\Pedidos\CrearPedido;
use App\Http\Controllers\Controller;
use App\Http\Resources\PedidoResource;
use App\Models\Pedido;
use App\Support\Idempotencia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Servicio 4 — Pedidos: solicitar, consultar y avanzar el estado (máquina de estados RF-24).
 */
class PedidoApiController extends Controller
{
    private const RELACIONES = [
        'servicio:id,titulo,precio_base,perfil_prestador_id',
        'servicio.perfilPrestador:id,user_id',
        'servicio.perfilPrestador.user:id,nombre',
        'cliente:id,nombre',
    ];

    /** Pedidos donde el usuario es cliente o prestador. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'estado'   => ['nullable', 'string', Rule::in(Pedido::ESTADOS)],
            'per_page' => ['nullable', 'integer', 'between:1,50'],
        ]);

        $usuario = $request->user();

        $pedidos = Pedido::query()
            ->with(self::RELACIONES)
            ->where(function ($donde) use ($usuario) {
                $donde->where('cliente_id', $usuario->id)
                    ->orWhereHas('servicio.perfilPrestador', fn ($p) => $p->where('user_id', $usuario->id));
            })
            ->when($filtros['estado'] ?? null, fn ($q, $estado) => $q->where('estado', $estado))
            ->latest('id')
            ->paginate((int) ($filtros['per_page'] ?? 12))
            ->withQueryString();

        return PedidoResource::collection($pedidos);
    }

    public function show(Request $request, Pedido $pedido): PedidoResource
    {
        $pedido->load(self::RELACIONES);

        // Quien no es parte del pedido recibe 404 (no se revela que existe).
        abort_if(CambiarEstadoPedido::actoresDe($request->user(), $pedido) === [], 404);

        return new PedidoResource($pedido);
    }

    public function store(Request $request, CrearPedido $crear): JsonResponse
    {
        $datos = $request->validate([
            'servicio_id'        => ['required', 'integer', 'exists:servicios,id'],
            'direccion_servicio' => ['required', 'string', 'max:255'],
            'fecha_deseada'      => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $usuario = $request->user();

        // Con la cabecera Idempotency-Key, reintentar no crea un segundo pedido.
        $resultado = Idempotencia::ejecutar(
            $request,
            'api.pedidos.store',
            fn () => ['pedido_id' => $crear->ejecutar($usuario, $datos)->id],
        );

        $pedido = Pedido::with(self::RELACIONES)->findOrFail($resultado['pedido_id']);

        return (new PedidoResource($pedido))
            ->response()
            ->setStatusCode(($resultado['duplicado'] ?? false) ? 200 : 201);
    }

    public function updateEstado(Request $request, Pedido $pedido, CambiarEstadoPedido $accion): JsonResponse|PedidoResource
    {
        $request->validate([
            'estado' => ['required', 'string', Rule::in(Pedido::ESTADOS)],
        ]);

        $usuario = $request->user();

        $pedido->loadMissing(['servicio.perfilPrestador.user', 'cliente']);

        $actores = CambiarEstadoPedido::actoresDe($usuario, $pedido);

        abort_if($actores === [], 404);

        $error = $accion->ejecutar($pedido, $request->input('estado'), $actores, $usuario);

        if ($error !== null) {
            return response()->json(['message' => $error], 422);
        }

        return new PedidoResource($pedido->fresh(self::RELACIONES));
    }
}
