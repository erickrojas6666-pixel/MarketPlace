<?php

namespace App\Notifications;

use App\Models\Pedido;
use App\Notifications\Channels\WebPushChannel;
use App\Services\WebPushService;
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

    // 'database' guarda en la tabla 'notifications'; Web Push avisa al dispositivo
    // (solo si hay llaves VAPID configuradas).
    public function via($notifiable): array
    {
        $canales = ['database'];

        if (app(WebPushService::class)->configurado()) {
            $canales[] = WebPushChannel::class;
        }

        return $canales;
    }

    /**
     * Mensaje corto y sin datos sensibles: se ve en la pantalla de bloqueo.
     *
     * @return array{titulo: string, cuerpo: string, url: string, tag: string}
     */
    public function toWebPush($notifiable): array
    {
        $cuerpo = $this->nuevoEstado === Pedido::PENDIENTE
            ? "Tienes una nueva solicitud de servicio (#{$this->pedido->id})."
            : "Tu pedido #{$this->pedido->id} ahora está: {$this->nuevoEstado}.";

        return [
            'titulo' => 'Actualización de pedido',
            'cuerpo' => $cuerpo,
            'url'    => '/dashboard',
            'tag'    => 'pedido-'.$this->pedido->id,
        ];
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