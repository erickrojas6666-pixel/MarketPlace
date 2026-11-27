<?php

namespace App\Http\Controllers;

use App\Actions\Disputas\AbrirDisputa;
use App\Models\Pedido;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** RF-38 — El cliente abre una disputa desde el detalle de su pedido completado. */
class PedidoDisputaController extends Controller
{
    public function store(Request $request, Pedido $pedido, AbrirDisputa $abrir): RedirectResponse
    {
        $usuario = $request->user();

        // Módulo apagado, no es el cliente, el trabajo no está completado o ya hay disputa.
        abort_unless($usuario->can('abrirDisputa', $pedido), 403);

        $datos = $request->validate([
            'motivo' => ['required', 'string', 'min:20', 'max:1500'],
        ], [
            'motivo.required' => 'Cuéntanos qué pasó con el trabajo.',
            'motivo.min'      => 'Describe el problema con al menos :min caracteres para que el mediador pueda evaluarlo.',
            'motivo.max'      => 'El motivo no puede pasar de :max caracteres.',
        ]);

        $abrir->ejecutar($usuario, $pedido, $datos['motivo']);

        return redirect()->back()->with(
            'success',
            'Abrimos tu disputa. Un mediador la revisará y el pago sigue retenido hasta que decida.'
        );
    }
}
