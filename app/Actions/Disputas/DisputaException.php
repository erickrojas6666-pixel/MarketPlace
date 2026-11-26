<?php

namespace App\Actions\Disputas;

use RuntimeException;

/**
 * Regla de negocio de las disputas que impide la acción (caso ya resuelto, caso tomado por
 * otro mediador…). El mensaje está pensado para mostrarse tal cual al mediador.
 */
class DisputaException extends RuntimeException
{
}
