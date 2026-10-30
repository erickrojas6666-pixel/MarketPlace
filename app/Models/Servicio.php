<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Servicio
 * 
 * @property int $id
 * @property int $prestador_id
 * @property int $categoria_id
 * @property string $titulo
 * @property string $descripcion
 * @property string|null $alcance
 * @property bool $incluye_materiales
 * @property bool $activo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property Categoria $categoria
 * @property PerfilesPrestador $perfiles_prestador
 * @property Collection|NivelesServicio[] $niveles_servicios
 * @property Collection|Pedido[] $pedidos
 *
 * @package App\Models
 */
class Servicio extends Model
{
	protected $table = 'servicios';

	protected $casts = [
		'prestador_id' => 'int',
		'categoria_id' => 'int',
		'incluye_materiales' => 'bool',
		'activo' => 'bool'
	];

	protected $fillable = [
		'prestador_id',
		'categoria_id',
		'titulo',
		'descripcion',
		'alcance',
		'incluye_materiales',
		'activo'
	];

	public function categoria()
	{
		return $this->belongsTo(Categoria::class);
	}

	public function perfiles_prestador()
	{
		return $this->belongsTo(PerfilesPrestador::class, 'prestador_id');
	}

	public function niveles_servicios()
	{
		return $this->hasMany(NivelesServicio::class);
	}

	public function pedidos()
	{
		return $this->hasMany(Pedido::class);
	}
}
