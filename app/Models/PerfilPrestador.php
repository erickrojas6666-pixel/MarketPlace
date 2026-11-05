<?php

namespace App\Models;

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

    protected $table = 'perfiles_prestador';

    /** es_pro NO es asignable en masa: lo gobierna la suscripción. */
    protected $fillable = [
        'user_id',
        'descripcion',
        'ubicacion_aproximada',
        'estado_verificacion',
        'motivo_rechazo',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'es_pro'  => 'boolean',
    ];

    public function estaVerificado(): bool
    {
        return $this->estado_verificacion === self::ESTADO_VERIFICADO;
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
