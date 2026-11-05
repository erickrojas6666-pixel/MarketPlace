<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

/**
 * @property int $id
 * @property int $cliente_id
 * @property int $servicio_id
 * @property string $estado
 * @property string $direccion_servicio
 * @property \Carbon\Carbon|null $fecha_programada
 */
class Pedido extends Model
{
    use HasFactory;

    public const PENDIENTE   = 'pendiente';
    public const RECHAZADO   = 'rechazado';
    public const ACEPTADO    = 'aceptado';
    public const EN_PROGRESO = 'en_progreso';
    public const COMPLETADO  = 'completado';
    public const CANCELADO   = 'cancelado';

    /** Mismos valores que el ENUM de la BD. */
    public const ESTADOS = [
        self::PENDIENTE,
        self::RECHAZADO,
        self::ACEPTADO,
        self::EN_PROGRESO,
        self::COMPLETADO,
        self::CANCELADO,
    ];

    protected $table = 'pedidos';

    /** 'estado' sigue asignable porque los controladores actuales lo usan; pasará a la máquina de estados. */
    protected $fillable = [
        'cliente_id',
        'servicio_id',
        'estado',
        'direccion_servicio',
        'fecha_programada',
    ];

    protected $casts = [
        'cliente_id'       => 'integer',
        'servicio_id'      => 'integer',
        'fecha_programada' => 'datetime',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cliente_id');
    }

    /** Alias de cliente(): lo usan controladores y vistas existentes. */
    public function user(): BelongsTo
    {
        return $this->cliente();
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class);
    }

    /** El prestador se obtiene a través del servicio (pedidos no tiene prestador_id). */
    public function prestador(): HasOneThrough
    {
        return $this->hasOneThrough(
            PerfilPrestador::class,
            Servicio::class,
            'id',                    // servicios.id
            'id',                    // perfiles_prestador.id
            'servicio_id',           // pedidos.servicio_id
            'perfil_prestador_id'    // servicios.perfil_prestador_id
        );
    }

    public function resena(): HasOne
    {
        return $this->hasOne(Resena::class);
    }

    public function resenas(): HasMany
    {
        return $this->hasMany(Resena::class);
    }
}
