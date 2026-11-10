<?php

namespace App\Services;

use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Adapter de correo transaccional con dos modos (MARKETPLACE_CORREO):
 *  - resend (por defecto): API REST de Resend (https://resend.com/docs/api-reference/emails/send-email).
 *  - smtp: usa el mailer de Laravel configurado con MAIL_* (SMTP real, o MAIL_MAILER=log
 *    para probar sin enviar nada: el correo queda escrito en storage/logs/laravel.log).
 *
 * - Sin configuración no hace nada.
 * - Nunca lanza excepciones: un correo que falla no debe romper un pedido.
 * - No registra destinatarios ni contenido en el log, solo el código de respuesta.
 */
class ResendCorreo
{
    private const URL = 'https://api.resend.com/emails';

    /** Último motivo de fallo, solo para diagnóstico (php artisan correo:probar). */
    public ?string $diagnostico = null;

    private function modo(): string
    {
        return config('marketplace.correo', 'resend') === 'smtp' ? 'smtp' : 'resend';
    }

    public function configurado(): bool
    {
        if ($this->modo() === 'smtp') {
            // Con SMTP real se exige usuario (si no, intentaría conectarse a un servidor inexistente
            // en cada notificación). Con otros mailers (p. ej. "log") basta con elegirlos.
            return match (config('mail.default')) {
                'smtp'  => filled(config('mail.mailers.smtp.host')) && filled(config('mail.mailers.smtp.username')),
                default => true,
            };
        }

        return filled(config('services.resend.key'));
    }

    public function enviar(string $para, string $asunto, string $html, ?string $texto = null): bool
    {
        $this->diagnostico = null;

        if (! $this->configurado()) {
            $this->diagnostico = $this->modo() === 'smtp'
                ? 'Falta configurar MAIL_HOST y MAIL_USERNAME (SMTP) en el .env.'
                : 'Falta RESEND_API_KEY en el .env.';

            return false;
        }

        if ($this->modo() === 'smtp') {
            return $this->enviarPorMailer($para, $asunto, $html, $texto);
        }

        try {
            $respuesta = Http::withToken(config('services.resend.key'))
                ->acceptJson()
                ->timeout(8)
                ->post(self::URL, array_filter([
                    'from'    => config('services.resend.from') ?: config('mail.from.address'),
                    'to'      => [$para],
                    'subject' => $asunto,
                    'html'    => $html,
                    'text'    => $texto,
                ]));

            if ($respuesta->successful()) {
                return true;
            }

            $this->diagnostico = 'HTTP '.$respuesta->status().' - '.($respuesta->json('message') ?? $respuesta->body());
            Log::warning('Resend rechazó el correo', ['codigo' => $respuesta->status()]);
        } catch (Throwable $e) {
            $this->diagnostico = get_class($e).': '.$e->getMessage();
            Log::warning('Resend no respondió', ['tipo' => class_basename($e)]);
        }

        return false;
    }

    private function enviarPorMailer(string $para, string $asunto, string $html, ?string $texto): bool
    {
        try {
            Mail::html($html, function (Message $mensaje) use ($para, $asunto, $texto) {
                $mensaje->to($para)->subject($asunto);

                if ($texto) {
                    $mensaje->text($texto);
                }
            });

            return true;
        } catch (Throwable $e) {
            $this->diagnostico = get_class($e).': '.$e->getMessage();
            Log::warning('El correo no se pudo enviar por SMTP', ['tipo' => class_basename($e)]);

            return false;
        }
    }
}
