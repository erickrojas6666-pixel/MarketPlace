<?php

namespace App\Http\Controllers;

use App\Actions\Pagos\ConfirmarPagoMercadoPago;
use App\Models\Pedido;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Adonde regresa el cliente desde Mercado Pago. No se fía de los parámetros de la URL
 * (cualquiera puede escribir ?status=approved): solo toma el id del pago y lo verifica.
 */
class PagoRetornoController extends Controller
{
    public function __invoke(Request $request, ConfirmarPagoMercadoPago $confirmar): RedirectResponse
    {
        $paymentId = (string) ($request->query('payment_id') ?? $request->query('collection_id') ?? '');

        if ($paymentId === '' || ! ctype_digit($paymentId)) {
            return redirect()->route('dashboard')
                ->with('error', 'No recibimos la confirmación del pago. Si ya pagaste, se reflejará en unos minutos.');
        }

        $resultado = $confirmar->ejecutar($paymentId);

        if ($resultado === null) {
            return redirect()->route('dashboard')
                ->with('error', 'No pudimos verificar tu pago. Si ya pagaste, se reflejará en unos minutos.');
        }

        // Solo el cliente del pedido ve el resultado de su pago.
        $pedido = Pedido::find($resultado['pago']->pedido_id);
        abort_if($pedido === null || (int) $pedido->cliente_id !== (int) $request->user()->id, 404);

        return match ($resultado['resultado']) {
            'aprobado', 'ya_confirmado' => redirect()->route('dashboard')
                ->with('success', '¡Pago recibido! Tu dinero queda retenido hasta que confirmes el trabajo.'),
            'pendiente' => redirect()->route('dashboard')
                ->with('success', 'Tu pago está en proceso. Te avisaremos cuando se acredite.'),
            default => redirect()->route('dashboard')
                ->with('error', 'El pago no se completó. Puedes intentarlo de nuevo.'),
        };
    }
}
