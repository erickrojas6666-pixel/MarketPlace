<?php

namespace App\Console\Commands;

use App\Models\Mensaje;
use App\Models\Pedido;
use App\Support\Auditoria;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Retención de datos (LFPDPPP, minimización): borra las conversaciones de los pedidos que
 * ya terminaron (confirmado, cancelado o rechazado) una vez pasado el plazo.
 * Un pedido en disputa no es final, así que su chat se conserva hasta que se resuelva.
 */
class PurgarMensajesChat extends Command
{
    protected $signature = 'chat:purgar-mensajes {--dry-run : Solo muestra cuántos mensajes se borrarían}';

    protected $description = 'Elimina los mensajes del chat de pedidos terminados según la política de retención.';

    public function handle(): int
    {
        $dias = (int) config('marketplace.chat.retencion_dias');

        if ($dias < 1) {
            // Un valor inválido no debe borrar todo: se aborta sin tocar nada.
            $this->error('CHAT_RETENCION_DIAS debe ser un número de días mayor que 0.');

            return self::FAILURE;
        }

        $limite = now()->subDays($dias);

        $consulta = Mensaje::query()->whereHas(
            'pedido',
            fn ($pedido) => $pedido->whereIn('estado', Pedido::FINALES)->where('updated_at', '<', $limite)
        );

        if ($this->option('dry-run')) {
            $this->info("Se borrarían {$consulta->count()} mensaje(s).");

            return self::SUCCESS;
        }

        // Borrado y auditoría en la misma transacción: no hay purga sin rastro (RF-52).
        $borrados = DB::transaction(function () use ($consulta, $dias) {
            $borrados = $consulta->delete();

            if ($borrados > 0) {
                // Solo cantidades: nunca el contenido de las conversaciones.
                Auditoria::registrar('chat.mensajes_purgados', 'mensaje', 0, null, [
                    'mensajes' => $borrados,
                    'dias'     => $dias,
                ]);
            }

            return $borrados;
        });

        $this->info("Mensajes borrados: {$borrados}.");

        return self::SUCCESS;
    }
}
