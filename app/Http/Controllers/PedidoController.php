<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Models\Servicio;
use App\Notifications\EstadoPedidoActualizado;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PedidoController extends Controller
{
    /**
     * Máquina de estados: estado actual => estados a los que se puede pasar.
     * Los estados finales (rechazado, completado, cancelado) no tienen salida.
     */
    private const TRANSICIONES_PRESTADOR = [
        Pedido::PENDIENTE   => [Pedido::ACEPTADO, Pedido::RECHAZADO],
        Pedido::ACEPTADO    => [Pedido::EN_PROGRESO, Pedido::CANCELADO],
        Pedido::EN_PROGRESO => [Pedido::COMPLETADO, Pedido::CANCELADO],
    ];

    /** El cliente solo puede cancelar mientras el prestador no ha respondido. */
    private const TRANSICIONES_CLIENTE = [
        Pedido::PENDIENTE => [Pedido::CANCELADO],
    ];

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
     * Cambia el estado de un pedido respetando quién es el actor y el flujo permitido.
     */
    public function updateEstado(Request $request, Pedido $pedido): RedirectResponse
    {
        $user = Auth::user();

        $request->validate([
            'estado' => ['required', 'string', Rule::in(Pedido::ESTADOS)],
        ]);

        $nuevoEstado = $request->estado;

        $pedido->load(['servicio.perfilPrestador.user', 'cliente']);

        // Quién es quién: el prestador debe existir y coincidir (sin prestador, nadie lo es).
        $prestadorUser = $pedido->servicio?->perfilPrestador?->user;
        $esPrestador   = $prestadorUser !== null && $prestadorUser->id === $user->id;
        $esCliente     = $pedido->cliente_id === $user->id;

        if (! $esPrestador && ! $esCliente) {
            abort(403, 'No tienes permiso para gestionar este pedido.');
        }

        // La verificación del estado actual y el cambio van juntos y con el registro
        // bloqueado: dos clics simultáneos no pueden saltarse el flujo.
        $error = DB::transaction(function () use ($pedido, $nuevoEstado, $esPrestador, $esCliente) {
            $estadoActual = Pedido::whereKey($pedido->id)->lockForUpdate()->value('estado');

            $permitidos = [];
            if ($esPrestador) {
                $permitidos = array_merge($permitidos, self::TRANSICIONES_PRESTADOR[$estadoActual] ?? []);
            }
            if ($esCliente) {
                $permitidos = array_merge($permitidos, self::TRANSICIONES_CLIENTE[$estadoActual] ?? []);
            }

            if (! in_array($nuevoEstado, $permitidos, true)) {
                return "No puedes cambiar un pedido '{$estadoActual}' a '{$nuevoEstado}'.";
            }

            $pedido->update(['estado' => $nuevoEstado]);

            return null;
        });

        if ($error !== null) {
            return redirect()->back()->with('error', $error);
        }

        // Se avisa a la otra parte: si actuó el cliente, al prestador; si no, al cliente.
        $destinatario = $esCliente ? $prestadorUser : $pedido->cliente;
        $destinatario?->notify(new EstadoPedidoActualizado($pedido, $nuevoEstado));

        return redirect()->back()->with('success', "El pedido #{$pedido->id} ahora está '{$nuevoEstado}'.");
    }
}