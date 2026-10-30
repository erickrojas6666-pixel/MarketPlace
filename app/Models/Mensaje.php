<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Mensaje
 * 
 * @property int $id
 * @property int $pedido_id
 * @property int $autor_id
 * @property string $cuerpo
 * @property string|null $adjunto_ruta
 * @property Carbon|null $created_at
 * 
 * @property User $user
 * @property Pedido $pedido
 *
 * @package App\Models
 */
class Mensaje extends Model
{
	protected $table = 'mensajes';
	public $timestamps = false;

	protected $casts = [
		'pedido_id' => 'int',
		'autor_id' => 'int'
	];

	protected $fillable = [
		'pedido_id',
		'autor_id',
		'cuerpo',
		'adjunto_ruta'
	];

	public function user()
	{
		return $this->belongsTo(User::class, 'autor_id');
	}

	public function pedido()
	{
		return $this->belongsTo(Pedido::class);
	}
}
