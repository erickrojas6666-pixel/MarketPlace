<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mensaje del chat de un pedido (RF-22).
 *
 * Las columnas son las de la migración de `mensajes` (emisor_id, contenido) más las que
 * agrega 2026_11_24_000001_alinear_mensajes_para_el_chat (adjunto_*, leido_at).
 * El adjunto es una imagen en el disco PRIVADO: la ruta y el hash nunca viajan al
 * cliente (igual que en FotoEvidencia); se lee con URL firmada tras autorizar.
 *
 * @property int $id
 * @property int $pedido_id
 * @property int $emisor_id
 * @property string $contenido  puede ser '' si el mensaje es solo una imagen
 * @property string|null $adjunto_ruta
 * @property string|null $adjunto_hash
 * @property \Carbon\Carbon|null $leido_at
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property-read User $emisor
 * @property-read Pedido $pedido
 */
class Mensaje extends Model
{
    protected $table = 'mensajes';

    protected $fillable = [
        'pedido_id',
        'emisor_id',
        'contenido',
        'adjunto_ruta',
        'adjunto_hash',
    ];

    /** leido_at NO es asignable en masa: solo lo marca el flujo de lectura. */
    protected $hidden = ['adjunto_ruta', 'adjunto_hash'];

    protected $casts = [
        'pedido_id' => 'integer',
        'emisor_id' => 'integer',
        'leido_at'  => 'datetime',
    ];

    public function emisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'emisor_id');
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'pedido_id');
    }
}
