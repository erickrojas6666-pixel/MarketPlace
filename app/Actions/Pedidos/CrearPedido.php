<?php

namespace App\Actions\Pedidos;

use App\Models\Pedido;
use App\Models\Servicio;
use App\Models\User;
use App\Notifications\EstadoPedidoActualizado;
use App\Support\Auditoria;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * El cliente solicita un servicio (mismas reglas que el formulario web).
 * La usa la API; el controlador web conserva su propia copia.
 */
class CrearPedido
{
    /**
     * @param  array{servicio_id: int, direccion_servicio: string, fecha_deseada?: string|null}  $datos
     */
    public function ejecutar(User $cliente, array $datos): Pedido
    {
        $servicio = Servicio::with('perfilPrestador.user')->findOrFail($datos['servicio_id']);
        $perfil   = $servicio->perfilPrestador;

        // El servicio debe estar publicado y su prestador verificado (RF-08).
        if (! $servicio->activo || $perfil === null || ! $perfil->estaVerificado()) {
            throw ValidationException::withMessages([
                'servicio_id' => 'Este servicio no está disponible para contratación.',
            ]);
        }

        // Nadie puede contratar su propio servicio.
        if ((int) $perfil->user_id === (int) $cliente->id) {
            throw ValidationException::withMessages([
                'servicio_id' => 'No puedes contratar tu propio servicio.',
            ]);
        }

        $pedido = DB::transaction(function () use ($datos, $servicio, $cliente) {
            $pedido = Pedido::create([
                'cliente_id'         => $cliente->id,
                'servicio_id'        => $servicio->id,
                'estado'             => Pedido::PENDIENTE,
                'direccion_servicio' => $datos['direccion_servicio'],
                'fecha_programada'   => $datos['fecha_deseada'] ?? now()->addDays(1),
            ]);

            Auditoria::registrar('pedido.creado', 'pedido', $pedido->id, null, [
                'estado'      => Pedido::PENDIENTE,
                'servicio_id' => $servicio->id,
            ]);

            return $pedido;
        });

        $prestador = $perfil->user;
        if ($prestador) {
            $prestador->notify(new EstadoPedidoActualizado($pedido, Pedido::PENDIENTE));
        }

        return $pedido;
    }
}
