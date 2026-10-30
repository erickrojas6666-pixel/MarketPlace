<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Pedido
 * 
 * @property int $id
 * @property int $cliente_id
 * @property int $prestador_id
 * @property int $servicio_id
 * @property int $nivel_id
 * @property int $direccion_id
 * @property string $estado
 * @property float $precio_total
 * @property float $comision_plataforma
 * @property Carbon $agendado_para
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property User $user
 * @property Direccione $direccione
 * @property NivelesServicio $niveles_servicio
 * @property PerfilesPrestador $perfiles_prestador
 * @property Servicio $servicio
 * @property Collection|Cotizacione[] $cotizaciones
 * @property Disputa|null $disputa
 * @property Collection|Mensaje[] $mensajes
 * @property Pago|null $pago
 * @property Collection|Resena[] $resenas
 *
 * @package App\Models
 */
class Pedido extends Model
{
	protected $table = 'pedidos';

	protected $casts = [
		'cliente_id' => 'int',
		'prestador_id' => 'int',
		'servicio_id' => 'int',
		'nivel_id' => 'int',
		'direccion_id' => 'int',
		'precio_total' => 'float',
		'comision_plataforma' => 'float',
		'agendado_para' => 'datetime'
	];

	protected $fillable = [
		'cliente_id',
		'prestador_id',
		'servicio_id',
		'nivel_id',
		'direccion_id',
		'estado',
		'precio_total',
		'comision_plataforma',
		'agendado_para'
	];

	public function user()
	{
		return $this->belongsTo(User::class, 'cliente_id');
	}

	public function direccione()
	{
		return $this->belongsTo(Direccione::class, 'direccion_id');
	}

	public function niveles_servicio()
	{
		return $this->belongsTo(NivelesServicio::class, 'nivel_id');
	}

	public function perfiles_prestador()
	{
		return $this->belongsTo(PerfilesPrestador::class, 'prestador_id');
	}

	public function servicio()
	{
		return $this->belongsTo(Servicio::class);
	}

	public function cotizaciones()
	{
		return $this->hasMany(Cotizacione::class);
	}

	public function disputa()
	{
		return $this->hasOne(Disputa::class);
	}

	public function mensajes()
	{
		return $this->hasMany(Mensaje::class);
	}

	public function pago()
	{
		return $this->hasOne(Pago::class);
	}

	public function resenas()
	{
		return $this->hasMany(Resena::class);
	}
}
