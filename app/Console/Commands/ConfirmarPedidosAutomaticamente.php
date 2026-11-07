<?php

namespace App\Console\Commands;

use App\Actions\Pedidos\CambiarEstadoPedido;
use App\Models\Pedido;
use App\Support\Pedidos\TransicionesPedido;
use Illuminate\Console\Command;

class ConfirmarPedidosAutomaticamente extends Command
{
    protected $signature = 'pedidos:confirmar-automaticas';

    protected $description = 'RF-26: confirma los pedidos completados que el cliente no confirmó a tiempo';

    public function handle(CambiarEstadoPedido $accion): int
    {
        $horas  = max(1, (int) config('marketplace.horas_confirmacion_automatica'));
        $limite = now()->subHours($horas);
        $total  = 0;

        // "completado" es un estado de espera (solo sale por confirmar o disputar),
        // así que updated_at equivale al momento en que el prestador terminó.
        Pedido::where('estado', Pedido::COMPLETADO)
            ->where('updated_at', '<=', $limite)
            ->orderBy('id')
            ->chunkById(100, function ($pedidos) use ($accion, &$total) {
                foreach ($pedidos as $pedido) {
                    $error = $accion->ejecutar($pedido, Pedido::CONFIRMADO, [TransicionesPedido::SISTEMA]);

                    if ($error === null) {
                        $total++;
                    }
                }
            });

        $this->info("Pedidos confirmados automáticamente: {$total}");

        return self::SUCCESS;
    }
}
