<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $nombre
 * @property string $slug
 * @property string|null $descripcion
 */
class Categoria extends Model
{
    use HasFactory;

    protected $table = 'categorias';

    protected $fillable = [
        'nombre',
        'slug',
        'descripcion',
    ];

    // La columna slug es NOT NULL y UNIQUE en la BD: se genera al crear.
    protected static function booted(): void
    {
        static::creating(function (Categoria $categoria) {
            if (empty($categoria->slug)) {
                $categoria->slug = Str::slug($categoria->nombre);
            }
        });
    }

    public function servicios(): HasMany
    {
        return $this->hasMany(Servicio::class, 'categoria_id');
    }
}
