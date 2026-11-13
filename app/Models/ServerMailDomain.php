<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A domain of a site that has email (see Services\Mail\MailHosting).
 *
 * @property int $id
 * @property int $server_id
 * @property string $hostname
 * @property string|null $dkim
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property Server $server
 * @property \Illuminate\Database\Eloquent\Collection<int, ServerMailBox> $boxes
 */
class ServerMailDomain extends Model
{
    protected $table = 'server_mail_domains';

    protected $fillable = ['server_id', 'hostname', 'dkim'];

    protected $casts = ['server_id' => 'integer'];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function boxes(): HasMany
    {
        return $this->hasMany(ServerMailBox::class, 'mail_domain_id');
    }
}
