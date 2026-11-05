<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reseña del cliente sobre un pedido completado (MVP).
 *
 * @property int $id
 * @property int $pedido_id
 * @property int $cliente_id
 * @property int $servicio_id
 * @property int $calificacion
 * @property string|null $comentario
 */
class Resena extends Model
{
    protected $table = 'resenas';

    protected $fillable = [
        'pedido_id',
        'cliente_id',
        'servicio_id',
        'calificacion',
        'comentario',
    ];

    protected $casts = [
        'pedido_id'    => 'integer',
        'cliente_id'   => 'integer',
        'servicio_id'  => 'integer',
        'calificacion' => 'integer',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cliente_id');
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class);
    }
}
