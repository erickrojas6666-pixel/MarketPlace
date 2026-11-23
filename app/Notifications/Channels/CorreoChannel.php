<?php

namespace App\Notifications\Channels;

use App\Services\ResendCorreo;
use Illuminate\Notifications\Notification;

/**
 * Canal de notificaciones por correo (Resend). La notificación debe definir toCorreo().
 */
class CorreoChannel
{
    public function __construct(private readonly ResendCorreo $correo)
    {
    }

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toCorreo') || blank($notifiable->email ?? null)) {
            return;
        }

        $mensaje = $notification->toCorreo($notifiable);

        $this->correo->enviar($notifiable->email, $mensaje['asunto'], $mensaje['html'], $mensaje['texto'] ?? null);
    }
}
