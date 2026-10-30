<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class NivelesServicio
 * 
 * @property int $id
 * @property int $servicio_id
 * @property string $nivel
 * @property float $precio
 * @property int $duracion_min
 * @property string|null $detalles
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property Servicio $servicio
 * @property Collection|Pedido[] $pedidos
 *
 * @package App\Models
 */
class NivelesServicio extends Model
{
	protected $table = 'niveles_servicio';

	protected $casts = [
		'servicio_id' => 'int',
		'precio' => 'float',
		'duracion_min' => 'int'
	];

	protected $fillable = [
		'servicio_id',
		'nivel',
		'precio',
		'duracion_min',
		'detalles'
	];

	public function servicio()
	{
		return $this->belongsTo(Servicio::class);
	}

	public function pedidos()
	{
		return $this->hasMany(Pedido::class, 'nivel_id');
	}
}
