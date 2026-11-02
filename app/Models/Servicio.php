<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Servicio extends Model
{
    use HasFactory;

    protected $table = 'servicios';

    protected $fillable = [
        'perfil_prestador_id',
        'categoria_id',
        'titulo',
        'descripcion',
        'precio_base',
        'activo',
    ];

    public function perfilPrestador()
    {
        return $this->belongsTo(PerfilPrestador::class, 'perfil_prestador_id');
    }

    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }
}