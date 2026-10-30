<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Disputa
 * 
 * @property int $id
 * @property int $pedido_id
 * @property int $abierta_por
 * @property int|null $mediador_id
 * @property string $motivo
 * @property string $estado
 * @property string|null $resolucion_notas
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property User|null $user
 * @property Pedido $pedido
 *
 * @package App\Models
 */
class Disputa extends Model
{
	protected $table = 'disputas';

	protected $casts = [
		'pedido_id' => 'int',
		'abierta_por' => 'int',
		'mediador_id' => 'int'
	];

	protected $fillable = [
		'pedido_id',
		'abierta_por',
		'mediador_id',
		'motivo',
		'estado',
		'resolucion_notas'
	];

	public function user()
	{
		return $this->belongsTo(User::class, 'mediador_id');
	}

	public function pedido()
	{
		return $this->belongsTo(Pedido::class);
	}
}
