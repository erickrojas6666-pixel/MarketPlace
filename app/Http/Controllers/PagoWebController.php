<?php

namespace App\Http\Controllers;

use App\Actions\Pagos\IniciarPagoMercadoPago;
use App\Actions\Pagos\RetenerPago;
use App\Models\Pedido;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Botón "Pagar" del Dashboard: usa la pasarela configurada en MARKETPLACE_PASARELA.
 */
class PagoWebController extends Controller
{
    public function pagar(Request $request, Pedido $pedido, IniciarPagoMercadoPago $iniciar, RetenerPago $retener): RedirectResponse|Response
    {
        $cliente = $request->user();

        try {
            if (config('marketplace.pasarela') === 'mercadopago') {
                [, $url] = $iniciar->ejecutar($cliente, $pedido->id);

                // Redirección completa (fuera de la SPA) hacia el cobro de Mercado Pago.
                return Inertia::location($url);
            }

            $retener->ejecutar($cliente, $pedido->id);

            return redirect()->back()->with('success', 'Pago realizado: tu dinero queda retenido hasta que confirmes el trabajo.');
        } catch (ValidationException $e) {
            return redirect()->back()->with('error', collect($e->errors())->flatten()->first());
        } catch (HttpException $e) {
            // Solo los fallos de la pasarela (502/503) se muestran como aviso; un 404 sigue siendo 404.
            if (in_array($e->getStatusCode(), [502, 503], true)) {
                return redirect()->back()->with('error', $e->getMessage());
            }

            throw $e;
        }
    }
}
