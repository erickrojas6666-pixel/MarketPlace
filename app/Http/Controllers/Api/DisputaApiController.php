<?php

namespace App\Http\Controllers\Api;

use App\Actions\Disputas\AbrirDisputa;
use App\Actions\Pedidos\CambiarEstadoPedido;
use App\Http\Controllers\Controller;
use App\Http\Resources\DisputaResource;
use App\Models\Pedido;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Servicio 4b — Disputa del pedido (RF-38). El mediador resuelve desde el panel web. */
class DisputaApiController extends Controller
{
    public function store(Request $request, Pedido $pedido, AbrirDisputa $abrir): JsonResponse
    {
        $usuario = $request->user();

        // Quien no es parte del pedido recibe 404 (no se revela que existe).
        abort_if(CambiarEstadoPedido::actoresDe($usuario, $pedido) === [], 404);

        // Parte del pedido, pero sin derecho a reclamar (prestador, módulo apagado, estado, duplicado).
        abort_unless($usuario->can('abrirDisputa', $pedido), 403);

        $datos = $request->validate([
            'motivo' => ['required', 'string', 'min:20', 'max:1500'],
        ]);

        $disputa = $abrir->ejecutar($usuario, $pedido, $datos['motivo']);

        return (new DisputaResource($disputa))->response()->setStatusCode(201);
    }
}
