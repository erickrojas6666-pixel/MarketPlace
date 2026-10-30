<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Class User
 * 
 * @property int $id
 * @property string $nombre
 * @property string $email
 * @property string|null $telefono
 * @property string $password
 * @property string|null $google_id
 * @property Carbon|null $telefono_verificado_en
 * @property Carbon|null $email_verified_at
 * @property string|null $two_factor_code
 * @property bool $two_factor_enabled
 * @property Carbon|null $two_factor_expires_at
 * @property string $estado
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property Collection|Direccione[] $direcciones
 * @property Collection|Disputa[] $disputas
 * @property Collection|DocumentosVerificacion[] $documentos_verificacions
 * @property Collection|LogAuditorium[] $log_auditoria
 * @property Collection|Mensaje[] $mensajes
 * @property Collection|Pedido[] $pedidos
 * @property PerfilPrestador|null $perfiles_prestador
 * @property Collection|Resena[] $resenas
 * @property Collection|SuscripcionesPush[] $suscripciones_pushes
 *
 * @package App\Models
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;
	protected $table = 'users';

	protected $casts = [
		'telefono_verificado_en' => 'datetime',
		'email_verified_at' => 'datetime',
		'two_factor_enabled' => 'bool',
		'two_factor_expires_at' => 'datetime'
	];

	protected $hidden = [
		'password',
		'remember_token'
	];

	protected $fillable = [
		'nombre',
		'email',
		'telefono',
		'password',
		'google_id',
		'telefono_verificado_en',
		'email_verified_at',
		'two_factor_code',
		'two_factor_enabled',
		'two_factor_expires_at',
		'estado',
		'remember_token'
	];

	public function direcciones()
	{
		return $this->hasMany(Direccione::class);
	}

	public function disputas()
	{
		return $this->hasMany(Disputa::class, 'mediador_id');
	}

	public function documentos_verificacions()
	{
		return $this->hasMany(DocumentosVerificacion::class, 'revisado_por');
	}

	public function log_auditoria()
	{
		return $this->hasMany(LogAuditorium::class, 'actor_id');
	}

	public function mensajes()
	{
		return $this->hasMany(Mensaje::class, 'autor_id');
	}

	public function pedidos()
	{
		return $this->hasMany(Pedido::class, 'cliente_id');
	}

	public function perfilPrestador()
    {
        return $this->hasOne(PerfilPrestador::class, 'user_id');
    }

	public function resenas()
	{
		return $this->hasMany(Resena::class, 'destinatario_id');
	}

	public function suscripciones_pushes()
	{
		return $this->hasMany(SuscripcionesPush::class);
	}
}
