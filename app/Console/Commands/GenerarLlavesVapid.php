<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GenerarLlavesVapid extends Command
{
    protected $signature = 'push:generar-vapid';

    protected $description = 'Genera el par de llaves VAPID para las notificaciones push';

    public function handle(): int
    {
        try {
            $llaves = VAPID::createVapidKeys();
        } catch (\Throwable) {
            $this->error('No se pudieron generar las llaves (suele faltar la configuración de OpenSSL en Windows).');
            $this->line('Alternativa: npx web-push generate-vapid-keys');

            return self::FAILURE;
        }

        $this->info('Agrega esto a tu .env (la llave privada NO se comparte ni se sube al repositorio):');
        $this->newLine();
        $this->line('VAPID_PUBLIC_KEY='.$llaves['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$llaves['privateKey']);
        $this->line('VAPID_SUBJECT=mailto:tu-correo@ejemplo.com');

        return self::SUCCESS;
    }
}
