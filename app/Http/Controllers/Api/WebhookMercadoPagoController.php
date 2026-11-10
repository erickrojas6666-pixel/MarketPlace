<?php

namespace App\Http\Controllers\Api;

use App\Actions\Pagos\ConfirmarPagoMercadoPago;
use App\Http\Controllers\Controller;
use App\Services\MercadoPagoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Notificaciones (webhooks) de Mercado Pago. Ruta pública: la defensa es doble.
 *  1. Firma x-signature (si hay MP_WEBHOOK_SECRET).
 *  2. Aunque alguien la falsifique, el estado del pago se consulta a la API de Mercado
 *     Pago con nuestro token: una notificación falsa no puede marcar nada como pagado.
 */
class WebhookMercadoPagoController extends Controller
{
    public function recibir(Request $request, MercadoPagoService $mercadoPago, ConfirmarPagoMercadoPago $confirmar): JsonResponse
    {
        if (! $mercadoPago->configurado() || config('marketplace.pasarela') !== 'mercadopago') {
            return response()->json(['ok' => false], 503);
        }

        if (! $mercadoPago->firmaValida($request)) {
            return response()->json(['message' => 'Firma inválida.'], 401);
        }

        $tipo = (string) ($request->input('type') ?? $request->query('type') ?? $request->query('topic') ?? '');

        // PHP convierte "data.id" de la query string en "data_id".
        $paymentId = (string) ($request->input('data.id') ?? $request->query('data_id') ?? $request->query('id') ?? '');

        if ($tipo === 'payment' && $paymentId !== '') {
            $confirmar->ejecutar($paymentId); // idempotente; si falla, Mercado Pago reintenta
        }

        // Siempre 200 para lo demás: otros tipos de evento no nos interesan.
        return response()->json(['ok' => true]);
    }
}
