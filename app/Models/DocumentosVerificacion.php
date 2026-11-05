<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $perfil_id
 * @property string $tipo    ine_frente | ine_reverso | comprobante_domicilio | referencia_personal | antecedentes
 * @property string $ruta_cifrada
 * @property string $hash_sha256
 * @property string $estado  pendiente | valido | rechazado
 * @property int|null $revisado_por
 * @property \Carbon\Carbon|null $vence_en
 */
class DocumentosVerificacion extends Model
{
    public const TIPOS = [
        'ine_frente',
        'ine_reverso',
        'comprobante_domicilio',
        'referencia_personal',
        'antecedentes',
    ];

    public const ESTADOS = ['pendiente', 'valido', 'rechazado'];

    protected $table = 'documentos_verificacion';

    protected $fillable = [
        'perfil_id',
        'tipo',
        'ruta_cifrada',
        'hash_sha256',
        'estado',
        'revisado_por',
        'vence_en',
    ];

    protected $casts = [
        'perfil_id'    => 'integer',
        'revisado_por' => 'integer',
        'vence_en'     => 'date',
    ];

    /** La ruta nunca debe viajar al frontend. */
    protected $hidden = [
        'ruta_cifrada',
        'hash_sha256',
    ];

    public function perfil(): BelongsTo
    {
        return $this->belongsTo(PerfilPrestador::class, 'perfil_id');
    }

    public function revisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }
}
