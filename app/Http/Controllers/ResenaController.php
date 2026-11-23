<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Models\Resena;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ResenaController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'pedido_id'    => 'required|integer|exists:pedidos,id',
            'calificacion' => 'required|integer|min:1|max:5',
            'comentario'   => 'nullable|string|max:1000',
        ]);

        $pedido = Pedido::findOrFail($validated['pedido_id']);

        // Solo el cliente dueño del pedido puede reseñar
        if ($pedido->cliente_id !== Auth::id()) {
            abort(403, 'No tienes permiso para calificar este pedido.');
        }

        // RF-35: solo se califica un trabajo ya confirmado por el cliente
        // (o cuya disputa se resolvió a favor de liberar el pago).
        if ($pedido->estado !== Pedido::CONFIRMADO) {
            throw ValidationException::withMessages([
                'pedido_id' => 'Solo puedes calificar pedidos confirmados.',
            ]);
        }

        // Una reseña por pedido (la restricción unique en BD cubre la carrera)
        if (Resena::where('pedido_id', $pedido->id)->exists()) {
            throw ValidationException::withMessages([
                'pedido_id' => 'Este pedido ya fue calificado.',
            ]);
        }

        try {
            Resena::create([
                'pedido_id'    => $pedido->id,
                'cliente_id'   => Auth::id(),
                'servicio_id'  => $pedido->servicio_id,
                'calificacion' => $validated['calificacion'],
                'comentario'   => $validated['comentario'] ?? null,
            ]);
        } catch (QueryException $e) {
            // 1062 = entrada duplicada: dos envíos simultáneos
            if (($e->errorInfo[1] ?? null) === 1062) {
                throw ValidationException::withMessages([
                    'pedido_id' => 'Este pedido ya fue calificado.',
                ]);
            }

            throw $e;
        }

        return redirect()->back()->with('success', '¡Gracias por calificar el servicio!');
    }
}
