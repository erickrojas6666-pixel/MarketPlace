<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Pago
 * 
 * @property int $id
 * @property int $pedido_id
 * @property string $pasarela
 * @property string $referencia_externa
 * @property float $monto
 * @property string $estado
 * @property Carbon|null $retenido_hasta
 * @property string $idempotency_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property Pedido $pedido
 * @property Collection|Transaccione[] $transacciones
 *
 * @package App\Models
 */
class Pago extends Model
{
	protected $table = 'pagos';

	protected $casts = [
		'pedido_id' => 'int',
		'monto' => 'float',
		'retenido_hasta' => 'datetime'
	];

	protected $fillable = [
		'pedido_id',
		'pasarela',
		'referencia_externa',
		'monto',
		'estado',
		'retenido_hasta',
		'idempotency_key'
	];

	public function pedido()
	{
		return $this->belongsTo(Pedido::class);
	}

	public function transacciones()
	{
		return $this->hasMany(Transaccione::class);
	}
}
