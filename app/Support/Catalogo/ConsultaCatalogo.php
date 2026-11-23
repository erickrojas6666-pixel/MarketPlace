<?php

namespace App\Support\Catalogo;

use App\Models\PerfilPrestador;
use App\Models\Servicio;
use Illuminate\Database\Eloquent\Builder;

/**
 * Regla única de qué servicios son públicos: activos y de prestadores verificados.
 * La comparten el catálogo y la búsqueda de la API.
 */
final class ConsultaCatalogo
{
    public static function base(): Builder
    {
        return Servicio::query()
            // Del prestador solo viaja el nombre (nunca correo, teléfono ni coordenadas).
            ->with([
                'categoria:id,nombre',
                'perfilPrestador:id,user_id',
                'perfilPrestador.user:id,nombre',
                'niveles:id,servicio_id,nivel,precio,duracion_min,detalles',
            ])
            ->withAvg('resenas as calificacion_promedio', 'calificacion')
            ->withCount('resenas')
            ->activos()
            ->whereHas(
                'perfilPrestador',
                fn ($q) => $q->where('estado_verificacion', PerfilPrestador::ESTADO_VERIFICADO)
            );
    }

    public static function filtrar(Builder $consulta, ?int $categoriaId, ?string $texto): Builder
    {
        return $consulta
            ->when($categoriaId, fn ($q) => $q->where('categoria_id', $categoriaId))
            ->when($texto, function ($q) use ($texto) {
                // Se escapan % y _ para que el texto del usuario no funcione como comodín.
                $like = '%'.addcslashes($texto, '%_\\').'%';

                $q->where(function ($w) use ($like) {
                    $w->where('titulo', 'like', $like)
                      ->orWhere('descripcion', 'like', $like);
                });
            });
    }
}
