<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class LogAuditorium
 * 
 * @property int $id
 * @property int|null $actor_id
 * @property string $accion
 * @property string $entidad
 * @property int $entidad_id
 * @property array|null $datos_antes
 * @property array|null $datos_despues
 * @property string $ip_origen
 * @property Carbon $creada_en
 * 
 * @property User|null $user
 *
 * @package App\Models
 */
class LogAuditorium extends Model
{
	protected $table = 'log_auditoria';
	public $timestamps = false;

	protected $casts = [
		'actor_id' => 'int',
		'entidad_id' => 'int',
		'datos_antes' => 'json',
		'datos_despues' => 'json',
		'creada_en' => 'datetime'
	];

	protected $fillable = [
		'actor_id',
		'accion',
		'entidad',
		'entidad_id',
		'datos_antes',
		'datos_despues',
		'ip_origen',
		'creada_en'
	];

	public function user()
	{
		return $this->belongsTo(User::class, 'actor_id');
	}
}
