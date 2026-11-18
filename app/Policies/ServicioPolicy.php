<?php

namespace App\Policies;

use App\Models\Servicio;
use App\Models\User;

class ServicioPolicy
{
    /** Gestionar el portafolio: dueño del servicio y prestador verificado (RF-08). */
    public function gestionarFotos(User $user, Servicio $servicio): bool
    {
        $servicio->loadMissing('perfilPrestador');

        return $user->esPrestadorVerificado()
            && $servicio->perfilPrestador?->user_id === $user->id;
    }
}
