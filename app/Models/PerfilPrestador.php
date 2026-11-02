<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class PerfilesPrestador
 * 
 * @property int $id
 * @property int $user_id
 * @property string|null $descripcion
 * @property string|null $ubicacion_aproximada
 * @property string $estado_verificacion
 * @property string|null $motivo_rechazo
 * @property bool $es_pro
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property User $user
 * @property Collection|DocumentosVerificacion[] $documentos_verificacions
 * @property Collection|Pedido[] $pedidos
 * @property Collection|Servicio[] $servicios
 * @property Collection|ZonasCobertura[] $zonas_coberturas
 *
 * @package App\Models
 */
class PerfilPrestador extends Model
{
	protected $table = 'perfiles_prestador';

	protected $casts = [
		'user_id' => 'int',
		'es_pro' => 'bool'
	];

	protected $fillable = [
        'user_id',
        'descripcion',
        'ubicacion_aproximada',
        'estado_verificacion',
        'es_pro',
    ];

	public function user()
	{
		return $this->belongsTo(User::class);
	}

	public function documentos_verificacions()
	{
		return $this->hasMany(DocumentosVerificacion::class, 'perfil_id');
	}

	public function pedidos()
	{
		return $this->hasMany(Pedido::class, 'prestador_id');
	}

	public function servicios()
	{
		return $this->hasMany(Servicio::class, 'prestador_id');
	}

	public function zonas_coberturas()
	{
		return $this->hasMany(ZonasCobertura::class, 'prestador_id');
	}
}
