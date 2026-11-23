<?php

namespace App\Models;

use App\Services\AlmacenFotos;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Foto del portafolio de un servicio (pública).
 *
 * @property int $id
 * @property int $servicio_id
 * @property string $ruta
 * @property string $hash_sha256
 * @property string|null $pie
 * @property int $orden
 * @property-read string|null $url
 * @property-read string|null $url_miniatura
 */
class FotoTrabajo extends Model
{
    protected $table = 'fotos_trabajo';

    protected $fillable = [
        'servicio_id',
        'ruta',
        'hash_sha256',
        'pie',
        'orden',
    ];

    protected $hidden = ['ruta', 'hash_sha256'];

    protected $appends = ['url', 'url_miniatura'];

    protected $casts = [
        'servicio_id' => 'integer',
        'orden'       => 'integer',
    ];

    protected function url(): Attribute
    {
        return Attribute::get(
            fn () => app(AlmacenFotos::class)->urlPublica($this->attributes['ruta'] ?? null)
        );
    }

    protected function urlMiniatura(): Attribute
    {
        return Attribute::get(function () {
            $ruta = $this->attributes['ruta'] ?? null;

            return $ruta ? app(AlmacenFotos::class)->urlPublica(AlmacenFotos::miniaturaDe($ruta)) : null;
        });
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class, 'servicio_id');
    }
}
