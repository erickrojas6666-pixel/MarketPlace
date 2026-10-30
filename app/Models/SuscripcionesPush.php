<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class SuscripcionesPush
 * 
 * @property int $id
 * @property int $user_id
 * @property string $endpoint
 * @property string $p256dh_key
 * @property string $auth_token_cifrado
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property User $user
 *
 * @package App\Models
 */
class SuscripcionesPush extends Model
{
	protected $table = 'suscripciones_push';

	protected $casts = [
		'user_id' => 'int'
	];

	protected $fillable = [
		'user_id',
		'endpoint',
		'p256dh_key',
		'auth_token_cifrado'
	];

	public function user()
	{
		return $this->belongsTo(User::class);
	}
}
