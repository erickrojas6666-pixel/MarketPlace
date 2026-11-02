<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class EstadoPedidoActualizado extends Notification
{
    use Queueable;

    public $pedido;
    public $nuevoEstado;

    public function __construct($pedido, $nuevoEstado)
    {
        $this->pedido = $pedido;
        $this->nuevoEstado = $nuevoEstado;
    }

    // Define que se guarde en la tabla 'notifications' de MySQL
    public function via($notifiable): array
    {
        return ['database'];
    }

    // Datos que se convertirán en JSON dentro de la columna 'data'
    public function toArray($notifiable): array
    {
        return [
            'titulo'    => 'Actualización de Pedido',
            'mensaje'   => "Tu pedido #{$this->pedido->id} ha cambiado al estado: '{$this->nuevoEstado}'.",
            'pedido_id' => $this->pedido->id,
            'estado'    => $this->nuevoEstado,
        ];
    }
}