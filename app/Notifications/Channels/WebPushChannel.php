<?php

namespace App\Notifications\Channels;

use App\Services\WebPushService;
use Illuminate\Notifications\Notification;

/**
 * Canal de notificaciones "Web Push". La notificación debe definir toWebPush().
 */
class WebPushChannel
{
    public function __construct(private readonly WebPushService $push)
    {
    }

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWebPush')) {
            return;
        }

        $this->push->enviar($notifiable, $notification->toWebPush($notifiable));
    }
}
