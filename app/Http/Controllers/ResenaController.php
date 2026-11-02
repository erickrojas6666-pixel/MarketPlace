<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Models\Resena;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ResenaController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'pedido_id'    => 'required|exists:pedidos,id',
            'calificacion' => 'required|integer|min:1|max:5',
            'comentario'   => 'nullable|string|max:1000',
        ]);

        $pedido = Pedido::with('servicio')->findOrFail($validated['pedido_id']);

        // Verificación de seguridad: solo el cliente dueño del pedido puede reseñar
        if ($pedido->cliente_id !== Auth::id()) {
            abort(403, 'No tienes permiso para calificar este pedido.');
        }

        // Construcción limpia adaptada a la estructura real de la BD
        $datosResena = [
            'pedido_id'    => $pedido->id,
            'cliente_id'   => Auth::id(),
            'calificacion' => $validated['calificacion'],
            'comentario'   => $validated['comentario'] ?? '',
        ];

        // Asignación opcional de servicio_id si existe la columna en resenas
        if ($pedido->servicio_id) {
            $datosResena['servicio_id'] = $pedido->servicio_id;
        }

        Resena::create($datosResena);

        return redirect()->back()->with('success', '¡Gracias por calificar el servicio!');
    }
}