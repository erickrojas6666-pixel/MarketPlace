<?php

namespace App\Actions\Disputas;

use App\Models\Disputa;
use App\Models\User;
use App\Support\Auditoria;
use Illuminate\Support\Facades\DB;

/**
 * Un mediador se asigna un caso pendiente ("tomarlo") para que nadie más lo resuelva a la vez.
 * Quien llama debe haber comprobado ya la autorización (DisputaPolicy::mediar).
 */
class TomarDisputa
{
    /** @throws DisputaException si el caso ya está resuelto o lo tiene otro mediador */
    public function ejecutar(User $mediador, int $disputaId): Disputa
    {
        return DB::transaction(function () use ($mediador, $disputaId) {
            $disputa = Disputa::whereKey($disputaId)->lockForUpdate()->firstOrFail();

            if (! $disputa->estaAbierta()) {
                throw new DisputaException('Esta disputa ya fue resuelta.');
            }

            if (! $disputa->puedeSerGestionadaPor($mediador)) {
                throw new DisputaException('Otro mediador ya tomó este caso.');
            }

            // Tomarla de nuevo (el mismo mediador) no cambia nada.
            if ($disputa->mediador_id === $mediador->id && $disputa->estado === Disputa::EN_REVISION) {
                return $disputa;
            }

            $antes = ['estado' => $disputa->estado, 'mediador_id' => $disputa->mediador_id];

            $disputa->update([
                'estado'      => Disputa::EN_REVISION,
                'mediador_id' => $mediador->id,
            ]);

            Auditoria::registrar('disputa.tomada', 'disputa', $disputa->id, $antes, [
                'estado'      => Disputa::EN_REVISION,
                'mediador_id' => $mediador->id,
            ]);

            return $disputa;
        });
    }
}
