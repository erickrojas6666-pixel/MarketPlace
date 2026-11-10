<?php

namespace App\Notifications;

use App\Models\Pedido;
use App\Notifications\Channels\CorreoChannel;
use App\Notifications\Channels\WebPushChannel;
use App\Services\ResendCorreo;
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

        // Correo transaccional (Resend): solo si hay llave configurada.
        if (app(ResendCorreo::class)->configurado()) {
            $canales[] = CorreoChannel::class;
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

    /**
     * Correo breve y sin datos sensibles (sin dirección, teléfono ni montos).
     *
     * @return array{asunto: string, html: string, texto: string}
     */
    public function toCorreo($notifiable): array
    {
        $id     = $this->pedido->id;
        $nombre = e($notifiable->nombre ?? 'Hola');
        $estado = e((string) $this->nuevoEstado);
        $enlace = e(url('/dashboard'));

        $mensaje = $this->nuevoEstado === Pedido::PENDIENTE
            ? "Tienes una nueva solicitud de servicio (pedido #{$id})."
            : "Tu pedido #{$id} ahora está: {$estado}.";

        $html = "<div style=\"font-family:Arial,sans-serif;max-width:480px;margin:auto;color:#1f2937\">"
            ."<h2 style=\"color:#14305D\">OficiosYa</h2>"
            ."<p>Hola, {$nombre}.</p>"
            ."<p>{$mensaje}</p>"
            ."<p><a href=\"{$enlace}\" style=\"background:#14305D;color:#fff;padding:10px 18px;border-radius:8px;text-decoration:none\">Ver mis pedidos</a></p>"
            ."<p style=\"font-size:12px;color:#6b7280\">Recibes este correo porque tienes una cuenta en OficiosYa.</p>"
            ."</div>";

        return [
            'asunto' => "OficiosYa: actualización del pedido #{$id}",
            'html'   => $html,
            'texto'  => strip_tags($mensaje)."\nVer mis pedidos: ".url('/dashboard'),
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