<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Categoria extends Model
{
    use HasFactory;

    protected $table = 'categorias';

    protected $fillable = [
        'nombre',
        'slug',
        'descripcion',
        'icono',
    ];

    // Generar slug automáticamente al asignar el nombre
    public static function boot()
    {
        parent::boot();

        static::creating(function ($categoria) {
            if (empty($categoria->slug)) {
                $categoria->slug = Str::slug($categoria->nombre);
            }
        });
    }

    // Relación: Una categoría tiene muchos servicios
    public function servicios()
    {
        return $this->hasMany(Servicio::class, 'categoria_id');
    }
}