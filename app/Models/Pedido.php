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
 * @property-read User $cliente
 * @property-read User $user
 * @property-read Servicio $servicio
 * @property-read PerfilPrestador|null $prestador
 * @property-read Resena|null $resena
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Resena> $resenas
 */
class Pedido extends Model
{
    use HasFactory;

    // Flujo (RF-24): pendiente(solicitado) → cotizado → aceptado → pagado → en_progreso
    //                → completado → confirmado / en_disputa / cancelado
    public const PENDIENTE   = 'pendiente';
    public const COTIZADO    = 'cotizado';
    public const RECHAZADO   = 'rechazado';
    public const ACEPTADO    = 'aceptado';
    public const PAGADO      = 'pagado';
    public const EN_PROGRESO = 'en_progreso';
    public const COMPLETADO  = 'completado';
    public const CONFIRMADO  = 'confirmado';
    public const EN_DISPUTA  = 'en_disputa';
    public const CANCELADO   = 'cancelado';

    /** Mismos valores que el ENUM de la BD. */
    public const ESTADOS = [
        self::PENDIENTE,
        self::COTIZADO,
        self::RECHAZADO,
        self::ACEPTADO,
        self::PAGADO,
        self::EN_PROGRESO,
        self::COMPLETADO,
        self::CONFIRMADO,
        self::EN_DISPUTA,
        self::CANCELADO,
    ];

    /** Estados sin salida: el pedido terminó. */
    public const FINALES = [
        self::RECHAZADO,
        self::CONFIRMADO,
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