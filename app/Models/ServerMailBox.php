<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A mailbox on the domain of a site.
 *
 * @property int $id
 * @property int $server_id
 * @property int $mail_domain_id
 * @property string $local_part
 * @property string $address
 * @property string $password
 * @property int $quota_mb
 * @property int|null $used_bytes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property ServerMailDomain $domain
 */
class ServerMailBox extends Model
{
    protected $table = 'server_mail_boxes';

    protected $fillable = ['server_id', 'mail_domain_id', 'local_part', 'address', 'password', 'quota_mb', 'used_bytes'];

    protected $hidden = ['password'];

    protected $casts = [
        'server_id' => 'integer',
        'mail_domain_id' => 'integer',
        'quota_mb' => 'integer',
        'used_bytes' => 'integer',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(ServerMailDomain::class, 'mail_domain_id');
    }
}
