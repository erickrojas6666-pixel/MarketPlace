<?php

namespace App\Services;

use App\Models\SuscripcionesPush;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * Adapter hacia la librería minishlink/web-push (Web Push + VAPID).
 *
 * - Si no hay llaves VAPID configuradas, no hace nada (la app sigue funcionando).
 * - El auth secret de cada suscripción se guarda cifrado (columna auth_token_cifrado).
 * - Las suscripciones caducadas (404/410) se eliminan solas.
 * - Los fallos nunca se propagan ni se registran con datos del usuario.
 */
class WebPushService
{
    /**
     * Motivos de los últimos fallos de enviar(), solo para diagnóstico en consola
     * (php artisan push:probar). No se guardan en el log de la aplicación porque
     * pueden contener la URL del endpoint de la suscripción.
     *
     * @var list<string>
     */
    public array $diagnostico = [];

    public function configurado(): bool
    {
        return filled(config('services.webpush.public_key'))
            && filled(config('services.webpush.private_key'));
    }

    /**
     * Anti-SSRF: el servidor hará POST al "endpoint" que mande el navegador, así que
     * solo se aceptan https y los servicios de push conocidos (config/services.php).
     */
    public static function hostPermitido(string $endpoint): bool
    {
        $partes = parse_url($endpoint);

        if (($partes['scheme'] ?? null) !== 'https' || empty($partes['host'])) {
            return false;
        }

        $host = strtolower($partes['host']);

        foreach ((array) config('services.webpush.hosts_permitidos', []) as $permitido) {
            if ($host === $permitido || str_ends_with($host, '.'.$permitido)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{titulo: string, cuerpo: string, url?: string, tag?: string}  $payload
     * @return int Cantidad de dispositivos a los que se entregó.
     */
    public function enviar(User $usuario, array $payload): int
    {
        $this->diagnostico = [];

        if (! $this->configurado()) {
            return 0;
        }

        $suscripciones = $usuario->suscripcionesPush()->get();

        if ($suscripciones->isEmpty()) {
            return 0;
        }

        try {
            $webPush = new WebPush(
                ['VAPID' => [
                    'subject'    => config('services.webpush.subject'),
                    'publicKey'  => config('services.webpush.public_key'),
                    'privateKey' => config('services.webpush.private_key'),
                ]],
                ['TTL' => 86400, 'urgency' => 'normal'],  // segundos de espera máxima por petición
            );

            foreach ($suscripciones as $s) {
                try {
                    $auth = Crypt::decryptString($s->auth_token_cifrado);
                } catch (Throwable) {
                    // Dato ilegible (p. ej. cambió APP_KEY): no sirve, el usuario debe volver a suscribirse.
                    $this->diagnostico[] = 'Suscripción ilegible (la APP_KEY cambió); se eliminó. Vuelve a activar las notificaciones.';
                    $s->delete();
                    continue;
                }

                $webPush->queueNotification(
                    Subscription::create([
                        'endpoint'        => $s->endpoint,
                        'publicKey'       => $s->p256dh_key,
                        'authToken'       => $auth,
                        'contentEncoding' => 'aes128gcm',
                    ]),
                    json_encode($payload, JSON_UNESCAPED_UNICODE),
                );
            }

            $entregadas = 0;

            foreach ($webPush->flush() as $reporte) {
                if ($reporte->isSuccess()) {
                    $entregadas++;
                } elseif ($reporte->isSubscriptionExpired()) {
                    $this->diagnostico[] = 'La suscripción caducó o fue revocada (404/410); se eliminó. Vuelve a activar las notificaciones.';
                    SuscripcionesPush::where('endpoint', $reporte->getEndpoint())->delete();
                } else {
                    $this->diagnostico[] = 'HTTP '.($reporte->getResponse()?->getStatusCode() ?? '?').' - '.$reporte->getReason();
                    Log::warning('Push no entregado', [
                        'codigo' => $reporte->getResponse()?->getStatusCode(),
                    ]);
                }
            }

            return $entregadas;
        } catch (Throwable $e) {
            $this->diagnostico[] = get_class($e).': '.$e->getMessage();
            Log::warning('Fallo al enviar push', ['tipo' => class_basename($e)]);

            return 0;
        }
    }
}