<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Pedido
 * 
 * @property int $id
 * @property int $cliente_id
 * @property int|null $prestador_id
 * @property int $servicio_id
 * @property int|null $nivel_id
 * @property int|null $direccion_id
 * @property string $estado
 * @property string|null $direccion_servicio
 * @property float|null $precio_total
 * @property float|null $comision_plataforma
 * @property Carbon|null $fecha_programada
 * @property Carbon|null $agendado_para
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property User $user
 * @property Direccione|null $direccione
 * @property NivelesServicio|null $niveles_servicio
 * @property PerfilesPrestador|null $perfiles_prestador
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
    use HasFactory;

    protected $table = 'pedidos';

    protected $casts = [
        'cliente_id'          => 'int',
        'prestador_id'        => 'int',
        'servicio_id'         => 'int',
        'nivel_id'            => 'int',
        'direccion_id'        => 'int',
        'precio_total'        => 'float',
        'comision_plataforma' => 'float',
        'fecha_programada'    => 'datetime',
        'agendado_para'       => 'datetime',
    ];

    protected $fillable = [
        'cliente_id',
        'prestador_id',
        'servicio_id',
        'nivel_id',
        'direccion_id',
        'estado',
        'direccion_servicio',
        'precio_total',
        'comision_plataforma',
        'fecha_programada',
        'agendado_para',
    ];

    // --- RELACIONES ORIGINALES ---

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
        return $this->belongsTo(PerfilPrestador::class, 'prestador_id');
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

    // --- ALIAS Y COMPATIBILIDAD CON VISTAS VUE/INERTIA ---

    /**
     * Alias de la relación cliente (mapea a la relación user)
     */
    public function cliente()
    {
        return $this->belongsTo(User::class, 'cliente_id');
    }

    /**
     * Alias en singular para obtener la última reseña del pedido
     */
    public function resena()
    {
        return $this->hasOne(Resena::class);
    }
}