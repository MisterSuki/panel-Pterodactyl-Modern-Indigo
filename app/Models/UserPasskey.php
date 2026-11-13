<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A passkey of an account (see Services\Auth\Passkeys).
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $rp_id
 * @property string $credential_id
 * @property string $public_key
 * @property int $algorithm
 * @property int $sign_count
 * @property string|null $transports
 * @property \Illuminate\Support\Carbon|null $last_used_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property User|null $user
 */
class UserPasskey extends Model
{
    protected $table = 'user_passkeys';

    protected $fillable = ['user_id', 'name', 'rp_id', 'credential_id', 'public_key', 'algorithm', 'sign_count', 'transports'];

    protected $hidden = ['public_key'];

    protected $casts = [
        'user_id' => 'integer',
        'algorithm' => 'integer',
        'sign_count' => 'integer',
        'last_used_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
