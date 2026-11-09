<?php

namespace App\Http\Controllers\Api;

use App\Actions\Pagos\LiberarPago;
use App\Actions\Pagos\RetenerPago;
use App\Http\Controllers\Controller;
use App\Http\Resources\PagoResource;
use App\Models\Pago;
use App\Models\Pedido;
use App\Support\Idempotencia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Servicio 5 — Pagos: retención (escrow) y liberación. Pasarela simulada por ahora.
 */
class PagoApiController extends Controller
{
    /** Pagos de los pedidos donde el usuario es cliente o prestador. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['per_page' => ['nullable', 'integer', 'between:1,50']]);

        $usuario = $request->user();

        $pagos = Pago::query()
            ->with('transacciones')
            ->whereHas('pedido', function ($pedido) use ($usuario) {
                $pedido->where(function ($donde) use ($usuario) {
                    $donde->where('cliente_id', $usuario->id)
                        ->orWhereHas('servicio.perfilPrestador', fn ($p) => $p->where('user_id', $usuario->id));
                });
            })
            ->latest('id')
            ->paginate($request->integer('per_page', 12))
            ->withQueryString();

        return PagoResource::collection($pagos);
    }

    public function show(Request $request, Pago $pago): PagoResource
    {
        $this->autorizarParte($request, $pago);

        return new PagoResource($pago->load('transacciones'));
    }

    /** El cliente paga: el dinero queda retenido por la plataforma. */
    public function store(Request $request, RetenerPago $retener): JsonResponse
    {
        $datos = $request->validate([
            'pedido_id' => ['required', 'integer', 'exists:pedidos,id'],
        ]);

        $usuario = $request->user();

        $resultado = Idempotencia::ejecutar(
            $request,
            'api.pagos.store',
            fn () => ['pago_id' => $retener->ejecutar($usuario, (int) $datos['pedido_id'])->id],
        );

        $pago = Pago::with('transacciones')->findOrFail($resultado['pago_id']);

        return (new PagoResource($pago))
            ->response()
            ->setStatusCode(($resultado['duplicado'] ?? false) ? 200 : 201);
    }

    /** Libera el pago al prestador (solo con el pedido confirmado). */
    public function liberar(Request $request, Pago $pago, LiberarPago $liberar): PagoResource
    {
        return new PagoResource($liberar->ejecutar($request->user(), $pago->id));
    }

    /** Solo el cliente, el prestador del pedido o un administrador pueden ver el pago. */
    private function autorizarParte(Request $request, Pago $pago): void
    {
        $usuario = $request->user();
        $pedido  = Pedido::with('servicio.perfilPrestador')->find($pago->pedido_id);

        $esCliente   = $pedido && (int) $pedido->cliente_id === (int) $usuario->id;
        $esPrestador = $pedido && (int) ($pedido->servicio?->perfilPrestador?->user_id) === (int) $usuario->id;

        abort_unless($esCliente || $esPrestador || $usuario->hasAnyRole(['admin', 'superadmin']), 404);
    }
}
