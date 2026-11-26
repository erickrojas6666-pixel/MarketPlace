<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Disputa de un pedido (RF-38): el cliente reporta un problema con el trabajo completado,
 * un mediador revisa la evidencia de ambas partes y decide a quién se le entrega el pago.
 *
 * @property int $id
 * @property int $pedido_id
 * @property int $abierta_por      cliente que la abrió
 * @property int|null $mediador_id mediador que tomó o resolvió el caso
 * @property string $motivo
 * @property string $estado
 * @property string|null $resolucion_notas
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property-read Pedido $pedido
 * @property-read User|null $mediador
 * @property-read User $abiertaPor
 */
class Disputa extends Model
{
    protected $table = 'disputas';

    // Estados (coinciden con el enum de la migración).
    public const ABIERTA                  = 'abierta';
    public const EN_REVISION              = 'en_revision';
    public const RESUELTA_FAVOR_CLIENTE   = 'resuelta_favor_cliente';
    public const RESUELTA_FAVOR_PRESTADOR = 'resuelta_favor_prestador';
    public const CANCELADA                = 'cancelada';

    /** Estados en los que el caso sigue pendiente de decisión. */
    public const ABIERTAS = [self::ABIERTA, self::EN_REVISION];

    // Decisión que envía el mediador al resolver.
    public const FAVOR_CLIENTE   = 'favor_cliente';
    public const FAVOR_PRESTADOR = 'favor_prestador';

    protected $casts = [
        'pedido_id'   => 'int',
        'abierta_por' => 'int',
        'mediador_id' => 'int',
    ];

    protected $fillable = [
        'pedido_id',
        'abierta_por',
        'mediador_id',
        'motivo',
        'estado',
        'resolucion_notas',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'pedido_id');
    }

    public function mediador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mediador_id');
    }

    public function abiertaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'abierta_por');
    }

    /** ¿Sigue pendiente de decisión? */
    public function estaAbierta(): bool
    {
        return in_array($this->estado, self::ABIERTAS, true);
    }

    /**
     * Un caso tomado por un mediador solo lo gestiona él o un superadmin.
     * (Que el mediador no sea parte del pedido lo decide DisputaPolicy.)
     */
    public function puedeSerGestionadaPor(User $user): bool
    {
        return $this->mediador_id === null
            || $this->mediador_id === $user->id
            || $user->hasRole('superadmin');
    }
}
