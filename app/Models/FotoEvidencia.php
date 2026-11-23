<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Evidencia fotográfica de un pedido. Privada: nunca se expone la ruta; el acceso
 * pasa por la ruta autenticada evidencias.ver, que redirige a una URL firmada de pocos minutos.
 *
 * @property int $id
 * @property int $pedido_id
 * @property int|null $subida_por
 * @property string $fase  antes | durante | despues
 * @property string $ruta
 * @property string $hash_sha256
 * @property string|null $pie
 */
class FotoEvidencia extends Model
{
    public const FASES = ['antes', 'durante', 'despues'];

    protected $table = 'fotos_evidencia';

    protected $fillable = [
        'pedido_id',
        'subida_por',
        'fase',
        'ruta',
        'hash_sha256',
        'pie',
    ];

    protected $hidden = ['ruta', 'hash_sha256'];

    protected $casts = [
        'pedido_id'  => 'integer',
        'subida_por' => 'integer',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'pedido_id');
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subida_por');
    }
}
