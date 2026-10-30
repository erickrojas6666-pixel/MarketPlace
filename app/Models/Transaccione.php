<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Transaccione
 * 
 * @property int $id
 * @property int $pago_id
 * @property string $tipo
 * @property float $monto
 * @property Carbon $creada_en
 * 
 * @property Pago $pago
 *
 * @package App\Models
 */
class Transaccione extends Model
{
	protected $table = 'transacciones';
	public $timestamps = false;

	protected $casts = [
		'pago_id' => 'int',
		'monto' => 'float',
		'creada_en' => 'datetime'
	];

	protected $fillable = [
		'pago_id',
		'tipo',
		'monto',
		'creada_en'
	];

	public function pago()
	{
		return $this->belongsTo(Pago::class);
	}
}
