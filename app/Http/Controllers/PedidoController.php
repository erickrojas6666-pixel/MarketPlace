<?php

namespace App\Http\Controllers;

use App\Actions\Pedidos\CambiarEstadoPedido;
use App\Models\Pedido;
use App\Models\Servicio;
use App\Notifications\EstadoPedidoActualizado;
use App\Support\Auditoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PedidoController extends Controller
{
    /**
     * El cliente solicita un servicio.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'servicio_id'        => 'required|integer|exists:servicios,id',
            'direccion_servicio' => 'required|string|max:255',
            'fecha_deseada'      => 'nullable|date|after_or_equal:today',
        ]);

        $servicio = Servicio::with('perfilPrestador.user')->findOrFail($validated['servicio_id']);
        $perfil   = $servicio->perfilPrestador;

        // El servicio debe estar publicado y su prestador verificado (RF-08):
        // el catálogo ya filtra esto, pero el endpoint no debe fiarse del frontend.
        if (! $servicio->activo || $perfil === null || ! $perfil->estaVerificado()) {
            throw ValidationException::withMessages([
                'servicio_id' => 'Este servicio no está disponible para contratación.',
            ]);
        }

        // Nadie puede contratar su propio servicio.
        if ($perfil->user_id === Auth::id()) {
            throw ValidationException::withMessages([
                'servicio_id' => 'No puedes contratar tu propio servicio.',
            ]);
        }

        // Insertar ÚNICAMENTE en las columnas que sí existen en MySQL:
        // cliente_id, servicio_id, estado, direccion_servicio, fecha_programada
        $pedido = DB::transaction(function () use ($validated, $servicio) {
            $pedido = Pedido::create([
                'cliente_id'         => Auth::id(),
                'servicio_id'        => $servicio->id,
                'estado'             => Pedido::PENDIENTE,
                'direccion_servicio' => $validated['direccion_servicio'],
                'fecha_programada'   => $validated['fecha_deseada'] ?? now()->addDays(1),
            ]);

            Auditoria::registrar('pedido.creado', 'pedido', $pedido->id, null, [
                'estado'      => Pedido::PENDIENTE,
                'servicio_id' => $servicio->id,
            ]);

            return $pedido;
        });

        // Notificar al prestador a través de las relaciones
        $prestadorUser = optional($servicio->perfilPrestador)->user;
        if ($prestadorUser) {
            $prestadorUser->notify(new EstadoPedidoActualizado($pedido, Pedido::PENDIENTE));
        }

        return redirect()->back()->with('success', '¡Pedido solicitado exitosamente!');
    }

    /**
     * Cambia el estado de un pedido. Las reglas viven en TransicionesPedido y
     * CambiarEstadoPedido (RF-24); aquí solo se valida la petición y se responde.
     */
    public function updateEstado(Request $request, Pedido $pedido, CambiarEstadoPedido $accion): RedirectResponse
    {
        $user = Auth::user();

        $request->validate([
            'estado' => ['required', 'string', Rule::in(Pedido::ESTADOS)],
        ]);

        $pedido->loadMissing(['servicio.perfilPrestador.user', 'cliente']);

        $actores = CambiarEstadoPedido::actoresDe($user, $pedido);

        if ($actores === []) {
            abort(403, 'No tienes permiso para gestionar este pedido.');
        }

        $error = $accion->ejecutar($pedido, $request->estado, $actores, $user);

        if ($error !== null) {
            return redirect()->back()->with('error', $error);
        }

        return redirect()->back()->with('success', "El pedido #{$pedido->id} ahora está '{$request->estado}'.");
    }
}
