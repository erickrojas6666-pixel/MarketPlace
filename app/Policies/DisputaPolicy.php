<?php

namespace App\Policies;

use App\Models\Disputa;
use App\Models\User;

/** Quién puede mediar una disputa (RF-38 / RF-49: mínimo privilegio). */
class DisputaPolicy
{
    /**
     * Ver la ficha, tomar el caso o resolverlo: solo mediador o superadmin, y nunca
     * quien sea parte del pedido (cliente o prestador). Así quien reclama o presta el
     * servicio no puede decidir su propio caso aunque tenga también el rol de mediador.
     */
    public function mediar(User $user, Disputa $disputa): bool
    {
        if (! $user->hasAnyRole(['mediador', 'superadmin'])) {
            return false;
        }

        $pedido = $disputa->pedido;

        if ($pedido === null) {
            return false;
        }

        return ! PedidoPolicy::esCliente($user, $pedido)
            && ! PedidoPolicy::esPrestador($user, $pedido);
    }
}
