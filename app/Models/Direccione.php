<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Direccione
 * 
 * @property int $id
 * @property int $user_id
 * @property string $calle_cifrada
 * @property string|null $referencias_cifradas
 * @property string $cp
 * @property string|null $colonia
 * @property string|null $municipio
 * @property string|null $estado
 * @property point $ubicacion_exacta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property User $user
 * @property Collection|Pedido[] $pedidos
 *
 * @package App\Models
 */
class Direccione extends Model
{
	protected $table = 'direcciones';

	protected $casts = [
		'user_id' => 'int',
		'ubicacion_exacta' => 'point'
	];

	protected $fillable = [
		'user_id',
		'calle_cifrada',
		'referencias_cifradas',
		'cp',
		'colonia',
		'municipio',
		'estado',
		'ubicacion_exacta'
	];

	public function user()
	{
		return $this->belongsTo(User::class);
	}

	public function pedidos()
	{
		return $this->hasMany(Pedido::class, 'direccion_id');
	}
}
