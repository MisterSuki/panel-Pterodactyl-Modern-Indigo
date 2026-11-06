<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A domain of a website. It comes from an own domain of the server or from one of its subdomains, which keep the DNS
 * part; this row is what the web agent of the node needs, and what it found out about the certificate.
 *
 * @property int $id
 * @property int $server_id
 * @property string $hostname
 * @property int|null $custom_domain_id
 * @property int|null $subdomain_id
 * @property bool $www
 * @property string $ssl_status
 * @property string|null $ssl_error
 * @property Server $server
 * @property ServerCustomDomain|null $custom
 * @property ServerSubdomain|null $subdomain
 */
class ServerWebDomain extends Model
{
    public const SSL_PENDING = 'pending';

    public const SSL_ACTIVE = 'active';

    public const SSL_FAILED = 'failed';

    protected $table = 'server_web_domains';

    protected $guarded = ['id'];

    protected $casts = ['www' => 'boolean'];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function custom(): BelongsTo
    {
        return $this->belongsTo(ServerCustomDomain::class, 'custom_domain_id');
    }

    public function subdomain(): BelongsTo
    {
        return $this->belongsTo(ServerSubdomain::class, 'subdomain_id');
    }

    /**
     * Whether the name leads to the server: a subdomain always does, an own domain once its DNS was checked.
     */
    public function ready(): bool
    {
        return $this->subdomain_id !== null || $this->custom?->verified_at !== null;
    }

    public function url(): string
    {
        return ($this->ssl_status === self::SSL_ACTIVE ? 'https://' : 'http://') . $this->hostname;
    }
}
