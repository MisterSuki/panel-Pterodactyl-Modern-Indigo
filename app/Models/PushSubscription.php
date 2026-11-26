<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A phone or a computer that gets the notifications of the panel (Web Push), for one person.
 *
 * @property int $id
 * @property int $user_id
 * @property string $endpoint where the push service of the browser waits for the messages
 * @property string $endpoint_hash
 * @property string $p256dh public key of the device (base64url)
 * @property string $auth secret of the device (base64url)
 * @property string|null $device
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class PushSubscription extends Model
{
    protected $table = 'push_subscriptions';

    protected $fillable = ['user_id', 'endpoint', 'endpoint_hash', 'p256dh', 'auth', 'device'];

    protected $casts = ['user_id' => 'integer'];

    protected $hidden = ['endpoint', 'p256dh', 'auth'];
}
