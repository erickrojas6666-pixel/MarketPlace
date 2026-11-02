<?php

namespace App\Notifications;

use App\Models\PerfilPrestador;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EstadoVerificacionNotification extends Notification
{
    use Queueable;

    protected $perfil;

    public function __construct(PerfilPrestador $perfil)
    {
        $this->perfil = $perfil;
    }

    /**
     * Canales de notificación (Database y Mail).
     */
    public function via(object $notifiable): array
    {
        return ['database']; // Puedes agregar 'mail' si tienes configurado SMTP
    }

    /**
     * Estructura almacenada en la base de datos (JSON).
     */
    public function toArray(object $notifiable): array
    {
        $estado = $this->perfil->estado_verificacion;
        
        $mensaje = match ($estado) {
            'aprobado' => '¡Felicidades! Tu perfil de prestador ha sido verificado y aprobado. Ya puedes ofrecer tus servicios.',
            'rechazado' => 'Tu solicitud de verificación fue rechazada. Motivo: ' . ($this->perfil->motivo_rechazo ?? 'No especificado.'),
            default => 'El estado de tu solicitud ha cambiado a: ' . $estado,
        };

        return [
            'titulo' => 'Actualización de Verificación',
            'mensaje' => $mensaje,
            'estado' => $estado,
            'perfil_id' => $this->perfil->id,
        ];
    }
}