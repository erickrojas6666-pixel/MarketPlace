<?php

namespace App\Actions\Disputas;

use App\Actions\Pedidos\CambiarEstadoPedido;
use App\Models\Disputa;
use App\Models\Pedido;
use App\Models\User;
use App\Support\Auditoria;
use App\Support\Pedidos\TransicionesPedido;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * El cliente reporta un problema con un pedido completado (RF-38).
 *
 * El pedido pasa a "en_disputa" y se crea el registro de la disputa en la misma transacción.
 * El pago NO se mueve: sigue retenido hasta que un mediador decida.
 * Quien llama debe haber comprobado ya la autorización (PedidoPolicy::abrirDisputa).
 */
class AbrirDisputa
{
    public function __construct(private readonly CambiarEstadoPedido $cambiarEstado)
    {
    }

    public function ejecutar(User $cliente, Pedido $pedido, string $motivo): Disputa
    {
        $disputa = DB::transaction(function () use ($cliente, $pedido, $motivo) {
            // El cambio de estado verifica la transición con el pedido bloqueado: dos clics
            // simultáneos no pueden abrir dos disputas.
            $error = $this->cambiarEstado->aplicar(
                $pedido,
                Pedido::EN_DISPUTA,
                [TransicionesPedido::CLIENTE],
                viaDisputa: true,
            );

            if ($error !== null) {
                throw ValidationException::withMessages(['pedido_id' => $error]);
            }

            $disputa = Disputa::create([
                'pedido_id'   => $pedido->id,
                'abierta_por' => $cliente->id,
                'motivo'      => $motivo,
                'estado'      => Disputa::ABIERTA,
            ]);

            Auditoria::registrar('disputa.abierta', 'disputa', $disputa->id, null, [
                'pedido_id' => $pedido->id,
                'estado'    => Disputa::ABIERTA,
            ]);

            return $disputa;
        });

        // Ya confirmado todo: se avisa al prestador (quien reclama no necesita aviso).
        $this->cambiarEstado->notificar(
            $pedido,
            Pedido::EN_DISPUTA,
            $cliente,
            'El cliente reportó un problema. Aporta tus fotos de evidencia.',
        );

        return $disputa;
    }
}
