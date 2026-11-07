<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $perfil_prestador_id
 * @property int $categoria_id
 * @property string $titulo
 * @property string $descripcion
 * @property string|null $precio_base
 * @property bool $activo
 * @property-read PerfilPrestador $perfilPrestador
 * @property-read Categoria $categoria
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Pedido> $pedidos
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Resena> $resenas
 */
class Servicio extends Model
{
    use HasFactory;

    protected $table = 'servicios';

    protected $fillable = [
        'perfil_prestador_id',
        'categoria_id',
        'titulo',
        'descripcion',
        'precio_base',
        'activo',
    ];

    protected $casts = [
        'precio_base' => 'decimal:2',
        'activo'      => 'boolean',
    ];

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    /**
     * Servicios cuyo prestador atiende el punto (lat, lng) dentro de $km.
     * Agrega el atributo `distancia_km` y ordena del más cercano al más lejano.
     *
     * La distancia va en una subconsulta (no en un JOIN) para no chocar con
     * columnas homónimas de perfiles_prestador (created_at, descripcion...).
     * Del prestador nunca sale su latitud/longitud, solo la distancia redondeada.
     */
    public function scopeCercanosA(Builder $query, float $lat, float $lng, float $km): Builder
    {
        if (empty($query->getQuery()->columns)) {
            $query->select('servicios.*');
        }

        return $query
            ->whereHas('perfilPrestador', fn ($q) => $q->cercanosA($lat, $lng, $km))
            ->addSelect([
                'distancia_km' => PerfilPrestador::query()
                    ->selectRaw('ROUND('.PerfilPrestador::SQL_DISTANCIA.', 1)', [$lat, $lng, $lat])
                    ->whereColumn('perfiles_prestador.id', 'servicios.perfil_prestador_id')
                    ->limit(1),
            ])
            ->orderBy('distancia_km');
    }

    public function perfilPrestador(): BelongsTo
    {
        return $this->belongsTo(PerfilPrestador::class, 'perfil_prestador_id');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class);
    }

    public function resenas(): HasMany
    {
        return $this->hasMany(Resena::class);
    }
}
