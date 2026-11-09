<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\WebPushService;
use Illuminate\Console\Command;

class ProbarPush extends Command
{
    protected $signature = 'push:probar {email : Correo del usuario}';

    protected $description = 'Envía una notificación push de prueba a los dispositivos de un usuario';

    public function handle(WebPushService $push): int
    {
        if (! $push->configurado()) {
            $this->error('Faltan VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY en el .env (ejecuta php artisan config:clear).');

            return self::FAILURE;
        }

        $usuario = User::where('email', $this->argument('email'))->first();

        if (! $usuario) {
            $this->error('No existe un usuario con ese correo.');

            return self::FAILURE;
        }

        $total = $usuario->suscripcionesPush()->count();
        $this->line("Dispositivos suscritos: {$total}");

        $entregadas = $push->enviar($usuario, [
            'titulo' => 'OficiosYa',
            'cuerpo' => 'Notificación de prueba: ¡las notificaciones funcionan!',
            'url'    => '/dashboard',
            'tag'    => 'prueba',
        ]);

        $this->info("Entregadas: {$entregadas}");

        if ($entregadas === 0 && $total > 0) {
            $this->newLine();
            $this->warn('No se entregó. Motivo(s):');

            foreach ($push->diagnostico ?: ['(sin detalle: revisa storage/logs/laravel.log)'] as $motivo) {
                $this->line(' - '.$motivo);
            }
        }

        return self::SUCCESS;
    }
}