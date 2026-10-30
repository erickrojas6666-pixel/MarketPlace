<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class ZonasCobertura
 * 
 * @property int $id
 * @property int $prestador_id
 * @property string|null $cp
 * @property float $radio_km
 * @property point $centro
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property PerfilesPrestador $perfiles_prestador
 *
 * @package App\Models
 */
class ZonasCobertura extends Model
{
	protected $table = 'zonas_cobertura';

	protected $casts = [
		'prestador_id' => 'int',
		'radio_km' => 'float',
		'centro' => 'point'
	];

	protected $fillable = [
		'prestador_id',
		'cp',
		'radio_km',
		'centro'
	];

	public function perfiles_prestador()
	{
		return $this->belongsTo(PerfilesPrestador::class, 'prestador_id');
	}
}
