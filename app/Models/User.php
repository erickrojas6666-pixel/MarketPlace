<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $nombre
 * @property string $email
 * @property string $password
 * @property Carbon|null $email_verified_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property PerfilPrestador|null $perfilPrestador
 * @property Collection|Pedido[] $pedidos
 * @property Collection|Resena[] $resenas
 * @property Collection|Direccione[] $direcciones
 * @property Collection|Mensaje[] $mensajes
 * @property Collection|Disputa[] $disputasAbiertas
 * @property Collection|Disputa[] $disputasMediadas
 * @property Collection|DocumentosVerificacion[] $documentosRevisados
 * @property Collection|LogAuditorium[] $registrosAuditoria
 * @property Collection|SuscripcionesPush[] $suscripcionesPush
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $table = 'users';

    /**
     * Solo lo que el propio usuario puede enviar desde un formulario.
     *
     * email_verified_at, estado, google_id, telefono_verificado_en y los campos
     * de 2FA NO van aquí: si algún día se hace ->fill($request->all()), nadie
     * podría verificarse la cuenta ni activarse el 2FA por asignación masiva.
     * Esos campos se escriben de forma explícita ($user->forceFill([...])->save()).
     */
    protected $fillable = [
        'nombre',
        'email',
        'password',
    ];

    /**
     * Nunca deben serializarse hacia Inertia/Vue ni hacia JSON. Se incluyen
     * google_id y two_factor_code por adelantado: si esas columnas se agregan
     * a la BD, ya nacen protegidas.
     */
    protected $hidden = [
        'password',
        'remember_token',
        'google_id',
        'two_factor_code',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
        ];
    }

    /* -----------------------------------------------------------------
     | Atajos de rol (evitan repetir cadenas de roles en controladores)
     |-----------------------------------------------------------------*/

    public function esAdmin(): bool
    {
        return $this->hasAnyRole(['admin', 'superadmin']);
    }

    public function esPrestador(): bool
    {
        return $this->hasRole('prestador');
    }

    /** Prestador con perfil aprobado por el Verificador (RF-08). */
    public function esPrestadorVerificado(): bool
    {
        return $this->esPrestador()
            && $this->perfilPrestador?->estaVerificado() === true;
    }

    /* -----------------------------------------------------------------
     | Relaciones (llaves foráneas verificadas contra marketplace.sql)
     |-----------------------------------------------------------------*/

    public function perfilPrestador(): HasOne
    {
        return $this->hasOne(PerfilPrestador::class, 'user_id');
    }

    /** Pedidos que el usuario hizo como cliente. */
    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class, 'cliente_id');
    }

    /** Reseñas que el usuario escribió como cliente (resenas.cliente_id). */
    public function resenas(): HasMany
    {
        return $this->hasMany(Resena::class, 'cliente_id');
    }

    public function direcciones(): HasMany
    {
        return $this->hasMany(Direccione::class, 'user_id');
    }

    /** Mensajes de chat enviados (mensajes.emisor_id). */
    public function mensajes(): HasMany
    {
        return $this->hasMany(Mensaje::class, 'emisor_id');
    }

    /** Disputas que el usuario abrió como cliente (disputas.abierta_por). */
    public function disputasAbiertas(): HasMany
    {
        return $this->hasMany(Disputa::class, 'abierta_por');
    }

    /** Disputas que atiende como mediador (disputas.mediador_id). */
    public function disputasMediadas(): HasMany
    {
        return $this->hasMany(Disputa::class, 'mediador_id');
    }

    /** Documentos de verificación que revisó (documentos_verificacion.revisado_por). */
    public function documentosRevisados(): HasMany
    {
        return $this->hasMany(DocumentosVerificacion::class, 'revisado_por');
    }

    /** Acciones del personal registradas en la bitácora (log_auditoria.actor_id). */
    public function registrosAuditoria(): HasMany
    {
        return $this->hasMany(LogAuditorium::class, 'actor_id');
    }

    public function suscripcionesPush(): HasMany
    {
        return $this->hasMany(SuscripcionesPush::class, 'user_id');
    }
}