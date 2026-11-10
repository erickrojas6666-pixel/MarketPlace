<?php

namespace App\Console\Commands;

use App\Services\ResendCorreo;
use Illuminate\Console\Command;

class ProbarCorreo extends Command
{
    protected $signature = 'correo:probar {email : Correo destino}';

    protected $description = 'Envía un correo de prueba (Resend o SMTP, según MARKETPLACE_CORREO)';

    public function handle(ResendCorreo $correo): int
    {
        if (! $correo->configurado()) {
            $this->error('Correo sin configurar: con MARKETPLACE_CORREO=resend falta RESEND_API_KEY; con smtp faltan MAIL_HOST y MAIL_USERNAME (luego php artisan config:clear).');

            return self::FAILURE;
        }

        $ok = $correo->enviar(
            $this->argument('email'),
            'OficiosYa: correo de prueba',
            '<p>¡El correo funciona! Este es un correo de prueba de <strong>OficiosYa</strong>.</p>',
            'El correo funciona. Prueba de OficiosYa.',
        );

        if ($ok) {
            $this->info('Correo enviado. Revisa la bandeja (y spam).');

            return self::SUCCESS;
        }

        $this->error('No se envió: '.$correo->diagnostico);

        return self::FAILURE;
    }
}
