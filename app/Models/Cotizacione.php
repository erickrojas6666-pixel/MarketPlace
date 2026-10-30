<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Cotizacione
 * 
 * @property int $id
 * @property int $pedido_id
 * @property float $precio_propuesto
 * @property Carbon $fecha_propuesta
 * @property string|null $notas
 * @property string $estado
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property Pedido $pedido
 *
 * @package App\Models
 */
class Cotizacione extends Model
{
	protected $table = 'cotizaciones';

	protected $casts = [
		'pedido_id' => 'int',
		'precio_propuesto' => 'float',
		'fecha_propuesta' => 'datetime'
	];

	protected $fillable = [
		'pedido_id',
		'precio_propuesto',
		'fecha_propuesta',
		'notas',
		'estado'
	];

	public function pedido()
	{
		return $this->belongsTo(Pedido::class);
	}
}
