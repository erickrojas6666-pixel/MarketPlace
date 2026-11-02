<?php

namespace App\Notifications;

use App\Models\PerfilPrestador;
use Illuminate\Bus\Queueable;
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
     * Canales de notificación (Database).
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Estructura almacenada en la base de datos (columna 'data' de la tabla 'notifications').
     */
    public function toArray(object $notifiable): array
    {
        $estado = $this->perfil->estado_verificacion;

        // Mapeo adaptado al ENUM físico de MySQL ('verificado', 'rechazado', 'pendiente')
        $mensaje = match ($estado) {
            'verificado', 'aprobado' => '¡Felicidades! Tu perfil de prestador ha sido verificado. Ya puedes ofrecer tus servicios en la plataforma.',
            'rechazado'             => 'Lamentablemente tu solicitud de verificación de prestador ha sido rechazada.',
            default                 => 'El estado de tu solicitud de verificación ha cambiado a: ' . $estado,
        };

        return [
            'titulo'    => 'Actualización de Verificación',
            'mensaje'   => $mensaje,
            'estado'    => $estado,
            'perfil_id' => $this->perfil->id,
        ];
    }
}