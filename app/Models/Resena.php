<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Resena
 * 
 * @property int $id
 * @property int $pedido_id
 * @property int $autor_id
 * @property int $destinatario_id
 * @property string $tipo
 * @property int $puntuacion
 * @property string|null $comentario
 * @property Carbon|null $publicada_en
 * @property Carbon|null $created_at
 * 
 * @property User $user
 * @property Pedido $pedido
 *
 * @package App\Models
 */
class Resena extends Model
{
	protected $table = 'resenas';
	public $timestamps = false;

	protected $casts = [
		'pedido_id' => 'int',
		'autor_id' => 'int',
		'destinatario_id' => 'int',
		'puntuacion' => 'int',
		'publicada_en' => 'datetime'
	];

	protected $fillable = [
    'pedido_id',
    'cliente_id',
    'prestador_id',
    'servicio_id',
    'calificacion',
    'comentario',
];
	public function user()
	{
		return $this->belongsTo(User::class, 'destinatario_id');
	}

	public function pedido()
	{
		return $this->belongsTo(Pedido::class);
	}

    public function cliente()
    {
        return $this->belongsTo(User::class, 'cliente_id');
    }
}
