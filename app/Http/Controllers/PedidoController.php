<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class PedidoController extends Controller
{
    // Crear solicitud de contratación (Cliente)
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'servicio_id' => 'required|exists:servicios,id',
            'direccion_servicio' => 'required|string|max:500',
            'fecha_programada' => 'nullable|date|after:now',
        ]);

        Pedido::create([
            'cliente_id' => Auth::id(),
            'servicio_id' => $request->servicio_id,
            'direccion_servicio' => $request->direccion_servicio,
            'fecha_programada' => $request->fecha_programada,
            'estado' => 'pendiente',
        ]);

        return redirect()->route('dashboard')->with('success', 'Solicitud enviada al prestador.');
    }

    // Actualizar estado del pedido (Aceptar/Completar/Cancelar)
    public function updateStatus(Request $request, Pedido $pedido): RedirectResponse
    {
        $request->validate([
            'estado' => 'required|in:aceptado,en_progreso,completado,cancelado',
        ]);

        $pedido->update(['estado' => $request->estado]);

        return redirect()->back()->with('success', 'Estado del pedido actualizado.');
    }
}