<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An own domain of a server: a name the user points at the server in their own DNS. The panel shows the records to
 * create and checks that they point at the server; it never changes the user's DNS.
 *
 * @property int $id
 * @property int $server_id
 * @property string $hostname
 * @property int|null $allocation_id
 * @property string|null $notes
 * @property \Carbon\Carbon|null $verified_at
 * @property Server $server
 * @property Allocation|null $allocation
 */
class ServerCustomDomain extends Model
{
    protected $table = 'server_custom_domains';

    protected $guarded = ['id'];

    protected $casts = ['verified_at' => 'datetime'];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(Allocation::class);
    }
}
