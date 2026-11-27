<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// RF-26: confirma los pedidos completados que el cliente no confirmó a tiempo.
// Requiere el cron de Laravel (`php artisan schedule:run` cada minuto) o `php artisan schedule:work` en desarrollo.
Schedule::command('pedidos:confirmar-automaticas')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();


// Retención de datos personales: elimina los archivos de INE/comprobantes ya resueltos tras el plazo.
Schedule::command('verificacion:purgar-documentos')
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->onOneServer();

// Retención de datos personales: borra las conversaciones de pedidos terminados tras el plazo.
Schedule::command('chat:purgar-mensajes')
    ->dailyAt('03:45')
    ->withoutOverlapping()
    ->onOneServer();
