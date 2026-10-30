<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class DocumentosVerificacion
 * 
 * @property int $id
 * @property int $perfil_id
 * @property string $tipo
 * @property string $ruta_cifrada
 * @property string $hash_sha256
 * @property string $estado
 * @property int|null $revisado_por
 * @property Carbon|null $vence_en
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property PerfilesPrestador $perfiles_prestador
 * @property User|null $user
 *
 * @package App\Models
 */
class DocumentosVerificacion extends Model
{
	protected $table = 'documentos_verificacion';

	protected $casts = [
		'perfil_id' => 'int',
		'revisado_por' => 'int',
		'vence_en' => 'datetime'
	];

	protected $fillable = [
		'perfil_id',
		'tipo',
		'ruta_cifrada',
		'hash_sha256',
		'estado',
		'revisado_por',
		'vence_en'
	];

	public function perfiles_prestador()
	{
		return $this->belongsTo(PerfilesPrestador::class, 'perfil_id');
	}

	public function user()
	{
		return $this->belongsTo(User::class, 'revisado_por');
	}
}
