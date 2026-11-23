<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Adapter hacia la API REST de Mercado Pago (Checkout Pro).
 * Docs: https://www.mercadopago.com.mx/developers/es/reference
 *
 * - El token vive solo en el servidor (MP_ACCESS_TOKEN); nunca llega al navegador.
 * - Nunca se confía en lo que diga una notificación: el estado real del pago
 *   siempre se consulta a la API con nuestro token.
 * - Los errores no se propagan ni se registran con datos del cliente.
 */
class MercadoPagoService
{
    private const BASE = 'https://api.mercadopago.com';

    public function configurado(): bool
    {
        return filled(config('services.mercadopago.access_token'));
    }

    public function sandbox(): bool
    {
        return (bool) config('services.mercadopago.sandbox');
    }

    /**
     * Crea la preferencia de pago (la pantalla de cobro de Mercado Pago).
     *
     * @return array{id: string, init_point: string, sandbox_init_point: ?string}|null
     */
    public function crearPreferencia(array $cuerpo): ?array
    {
        try {
            $respuesta = $this->http()->post(self::BASE.'/checkout/preferences', $cuerpo);

            if ($respuesta->successful() && filled($respuesta->json('id'))) {
                return [
                    'id'                 => (string) $respuesta->json('id'),
                    'init_point'         => (string) $respuesta->json('init_point'),
                    'sandbox_init_point' => $respuesta->json('sandbox_init_point'),
                ];
            }

            Log::warning('Mercado Pago rechazó la preferencia', ['codigo' => $respuesta->status()]);
        } catch (Throwable $e) {
            Log::warning('Mercado Pago no respondió', ['tipo' => class_basename($e)]);
        }

        return null;
    }

    /** Consulta un pago por su id. Es la única fuente de verdad del estado del pago. */
    public function obtenerPago(string $paymentId): ?array
    {
        // Solo dígitos: el id va dentro de la URL y no debe poder alterarla.
        if (! ctype_digit($paymentId)) {
            return null;
        }

        try {
            $respuesta = $this->http()->get(self::BASE.'/v1/payments/'.$paymentId);

            if ($respuesta->successful()) {
                return $respuesta->json();
            }

            Log::warning('Mercado Pago: pago no consultable', ['codigo' => $respuesta->status()]);
        } catch (Throwable $e) {
            Log::warning('Mercado Pago no respondió', ['tipo' => class_basename($e)]);
        }

        return null;
    }

    /**
     * Valida la cabecera x-signature de una notificación (HMAC-SHA256 con el secreto de la
     * aplicación). Sin MP_WEBHOOK_SECRET no se verifica, lo cual es seguro porque de todos
     * modos el pago se confirma contra la API.
     */
    public function firmaValida(Request $request): bool
    {
        $secreto = config('services.mercadopago.webhook_secret');

        if (blank($secreto)) {
            return true;
        }

        $ts = $v1 = null;
        foreach (explode(',', (string) $request->header('x-signature')) as $parte) {
            [$clave, $valor] = array_pad(explode('=', trim($parte), 2), 2, null);
            if ($clave === 'ts') {
                $ts = $valor;
            } elseif ($clave === 'v1') {
                $v1 = $valor;
            }
        }

        if ($ts === null || $v1 === null) {
            return false;
        }

        // PHP convierte el punto de "data.id" en guion bajo en la query string.
        $dataId    = strtolower((string) ($request->query('data_id') ?? $request->input('data.id') ?? ''));
        $requestId = (string) $request->header('x-request-id');

        $plantilla = ($dataId !== '' ? "id:{$dataId};" : '')
            .($requestId !== '' ? "request-id:{$requestId};" : '')
            ."ts:{$ts};";

        return hash_equals(hash_hmac('sha256', $plantilla, $secreto), $v1);
    }

    private function http()
    {
        return Http::withToken(config('services.mercadopago.access_token'))
            ->acceptJson()
            ->asJson()
            ->timeout(10);
    }
}
