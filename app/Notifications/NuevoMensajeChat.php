<?php

namespace App\Notifications;

use App\Notifications\Channels\WebPushChannel;
use App\Services\WebPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Aviso de mensaje nuevo en el chat de un pedido (RF-22).
 *
 * Es el mecanismo de tiempo real del chat: el Service Worker lo recibe, avisa a la app abierta
 * para que consulte lo nuevo y, si la app no está a la vista, muestra la notificación.
 *
 * Solo Web Push: dentro de la app el aviso es el contador de "sin leer", así que no se
 * guarda en la tabla de notificaciones. El texto NO incluye el mensaje (se ve en la
 * pantalla de bloqueo).
 */
class NuevoMensajeChat extends Notification
{
    use Queueable;

    public function __construct(public int $pedidoId)
    {
    }

    public function via($notifiable): array
    {
        return app(WebPushService::class)->configurado() ? [WebPushChannel::class] : [];
    }

    /** @return array{titulo: string, cuerpo: string, url: string, tag: string, tipo: string, pedido_id: int} */
    public function toWebPush($notifiable): array
    {
        return [
            'titulo' => 'Mensaje nuevo',
            'cuerpo' => "Tienes un mensaje nuevo en tu pedido #{$this->pedidoId}.",
            'url'    => '/dashboard?chat='.$this->pedidoId, // el dashboard abre esa conversación
            'tag'    => 'chat-'.$this->pedidoId, // los avisos de una conversación se reemplazan entre sí
            'tipo'      => 'chat',           // el Service Worker lo usa para avisar a la app abierta
            'pedido_id' => $this->pedidoId,
        ];
    }
}
