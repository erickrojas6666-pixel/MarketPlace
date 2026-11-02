<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Models\Servicio;
use App\Notifications\EstadoPedidoActualizado;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PedidoController extends Controller
{
    /**
     * El cliente solicita un servicio.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'servicio_id'        => 'required|exists:servicios,id',
            'direccion_servicio' => 'required|string|max:255',
            'fecha_deseada'      => 'nullable|date',
        ]);

        $servicio = Servicio::with('perfilPrestador.user')->findOrFail($validated['servicio_id']);

        // Insertar ÚNICAMENTE en las columnas que sí existen en MySQL:
        // cliente_id, servicio_id, estado, direccion_servicio, fecha_programada
        $pedido = Pedido::create([
            'cliente_id'         => Auth::id(),
            'servicio_id'        => $servicio->id,
            'estado'             => 'pendiente',
            'direccion_servicio' => $validated['direccion_servicio'],
            'fecha_programada'   => $validated['fecha_deseada'] ?? now()->addDays(1),
        ]);

        // Notificar al prestador a través de las relaciones
        $prestadorUser = optional($servicio->perfilPrestador)->user;
        if ($prestadorUser) {
            $prestadorUser->notify(new EstadoPedidoActualizado($pedido, 'pendiente'));
        }

        return redirect()->back()->with('success', '¡Pedido solicitado exitosamente!');
    }

    /**
     * Actualiza el estado del pedido con mapeo defensivo para MySQL ENUM y dispara notificaciones.
     */
    public function updateEstado(Request $request, Pedido $pedido): RedirectResponse
    {
        $user = Auth::user();

        $request->validate([
            'estado' => 'required|string',
        ]);

        // Mapeo defensivo para ENUM: convierte 'en_proceso' a 'en_progreso'
        $estadoInput = $request->estado;
        $nuevoEstado = ($estadoInput === 'en_proceso') ? 'en_progreso' : $estadoInput;

        if (!in_array($nuevoEstado, ['pendiente', 'aceptado', 'rechazado', 'en_progreso', 'completado', 'cancelado'])) {
            return redirect()->back()->with('error', 'Estado no válido.');
        }

        $pedido->load(['servicio.perfilPrestador.user', 'cliente', 'user']);

        // Validaciones de permisos / seguridad
        if ($nuevoEstado === 'cancelado') {
            $esCliente = $pedido->cliente_id === $user->id;
            $esPrestador = optional(optional($pedido->servicio)->perfilPrestador)->user_id === $user->id;

            if (!$esCliente && !$esPrestador) {
                abort(403, 'No tienes permiso para cancelar este pedido.');
            }
        } else {
            $prestadorUserId = optional(optional($pedido->servicio)->perfilPrestador)->user_id;
            
            if ($prestadorUserId && $prestadorUserId !== $user->id) {
                abort(403, 'No tienes permiso para gestionar este pedido.');
            }
        }

        $pedido->update([
            'estado' => $nuevoEstado,
        ]);

        // Notificaciones según quién actualizó el pedido
        $clienteUser = $pedido->cliente ?? $pedido->user;
        $prestadorUser = optional(optional($pedido->servicio)->perfilPrestador)->user;

        if ($clienteUser && $user->id === $clienteUser->id && $prestadorUser) {
            $prestadorUser->notify(new EstadoPedidoActualizado($pedido, $nuevoEstado));
        } elseif ($clienteUser) {
            $clienteUser->notify(new EstadoPedidoActualizado($pedido, $nuevoEstado));
        }

        return redirect()->back()->with('success', "El pedido #{$pedido->id} ahora está '{$nuevoEstado}'.");
    }
}