<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $descripcion
 * @property string|null $ubicacion_aproximada
 * @property string $estado_verificacion  pendiente | verificado | rechazado
 * @property string|null $motivo_rechazo
 * @property bool $es_pro
 * @property float|null $latitud
 * @property float|null $longitud
 * @property int $radio_cobertura_km
 */
class PerfilPrestador extends Model
{
    public const ESTADO_PENDIENTE  = 'pendiente';
    public const ESTADO_VERIFICADO = 'verificado';
    public const ESTADO_RECHAZADO  = 'rechazado';

    public const ESTADOS = [
        self::ESTADO_PENDIENTE,
        self::ESTADO_VERIFICADO,
        self::ESTADO_RECHAZADO,
    ];

    /**
     * Distancia en km (Haversine) entre un punto (3 parámetros: lat, lng, lat)
     * y la ubicación del perfil. Se reutiliza en filtros y en el cálculo de distancia.
     */
    public const SQL_DISTANCIA = '6371 * ACOS(LEAST(1, '
        .'COS(RADIANS(?)) * COS(RADIANS(perfiles_prestador.latitud)) '
        .'* COS(RADIANS(perfiles_prestador.longitud) - RADIANS(?)) '
        .'+ SIN(RADIANS(?)) * SIN(RADIANS(perfiles_prestador.latitud))))';

    protected $table = 'perfiles_prestador';

    /** es_pro NO es asignable en masa: lo gobierna la suscripción. */
    protected $fillable = [
        'user_id',
        'descripcion',
        'ubicacion_aproximada',
        'latitud',
        'longitud',
        'radio_cobertura_km',
        'estado_verificacion',
        'motivo_rechazo',
    ];

    protected $casts = [
        'user_id'            => 'integer',
        'es_pro'             => 'boolean',
        'latitud'            => 'float',
        'longitud'           => 'float',
        'radio_cobertura_km' => 'integer',
    ];

    public function estaVerificado(): bool
    {
        return $this->estado_verificacion === self::ESTADO_VERIFICADO;
    }

    public function tieneUbicacion(): bool
    {
        return $this->latitud !== null && $this->longitud !== null;
    }

    /**
     * Perfiles que atienden el punto dado: dentro del radio pedido por el cliente
     * Y dentro del radio de cobertura que el propio prestador declaró.
     *
     * Primero un recuadro (bounding box) que aprovecha el índice (latitud, longitud);
     * después la distancia exacta solo sobre los candidatos.
     */
    public function scopeCercanosA(Builder $query, float $lat, float $lng, float $km): Builder
    {
        $dLat = $km / 111.045;
        $dLng = $km / (111.045 * max(cos(deg2rad($lat)), 0.01));

        return $query
            ->whereNotNull('latitud')
            ->whereNotNull('longitud')
            ->whereBetween('latitud', [$lat - $dLat, $lat + $dLat])
            ->whereBetween('longitud', [$lng - $dLng, $lng + $dLng])
            ->whereRaw(
                '('.self::SQL_DISTANCIA.') <= LEAST(?, perfiles_prestador.radio_cobertura_km)',
                [$lat, $lng, $lat, $km]
            );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function servicios(): HasMany
    {
        return $this->hasMany(Servicio::class, 'perfil_prestador_id');
    }

    /** La tabla pedidos no tiene prestador_id: se llega a través del servicio. */
    public function pedidos(): HasManyThrough
    {
        return $this->hasManyThrough(Pedido::class, Servicio::class, 'perfil_prestador_id', 'servicio_id');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(DocumentosVerificacion::class, 'perfil_id');
    }
}
