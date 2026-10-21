<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A subdomain made by a server, and the DNS records created for it at the provider (kept so they can be removed).
 *
 * @property int $id
 * @property int $server_id
 * @property int $domain_id
 * @property string $name
 * @property int|null $allocation_id
 * @property string|null $notes
 * @property array|null $records
 * @property SubdomainDomain $domain
 * @property Server $server
 * @property Allocation|null $allocation
 */
class ServerSubdomain extends Model
{
    protected $table = 'server_subdomains';

    protected $guarded = ['id'];

    protected $casts = ['records' => 'array'];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(SubdomainDomain::class, 'domain_id');
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(Allocation::class);
    }

    public function fqdn(): string
    {
        return $this->name . '.' . ($this->domain->domain ?? '');
    }
}
