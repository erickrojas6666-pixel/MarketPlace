<?php

namespace App\Policies;

use App\Models\FotoEvidencia;
use App\Models\Pedido;
use App\Models\User;

class FotoEvidenciaPolicy
{
    public function ver(User $user, FotoEvidencia $foto): bool
    {
        return $user->can('verEvidencias', $foto->pedido);
    }

    /**
     * Solo quien la subió y solo mientras el trabajo no está cerrado ni en disputa:
     * la evidencia no puede desaparecer cuando alguien la necesita.
     */
    public function eliminar(User $user, FotoEvidencia $foto): bool
    {
        return $foto->subida_por === $user->id
            && in_array($foto->pedido->estado, [Pedido::EN_PROGRESO, Pedido::COMPLETADO], true);
    }
}
