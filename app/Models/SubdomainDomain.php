<?php

namespace Pterodactyl\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

/**
 * A domain the administration offers for subdomains, with the DNS provider that hosts its zone. The keys of the
 * provider are kept encrypted and never given back to a page.
 *
 * @property int $id
 * @property string $domain
 * @property string $provider
 * @property string $credentials
 * @property string|null $zone_id
 * @property bool $available
 */
class SubdomainDomain extends Model
{
    public const PROVIDERS = [
        'cloudflare' => 'Cloudflare',
        'bunny' => 'Bunny.net DNS',
        'powerdns' => 'PowerDNS',
    ];

    protected $table = 'subdomain_domains';

    protected $guarded = ['id'];

    protected $hidden = ['credentials'];

    protected $casts = ['available' => 'boolean'];

    public function subdomains(): HasMany
    {
        return $this->hasMany(ServerSubdomain::class, 'domain_id');
    }

    /**
     * @return array<string, string>
     */
    public function secrets(): array
    {
        try {
            $data = json_decode(Crypt::decryptString((string) $this->credentials), true);
        } catch (DecryptException) {
            return [];
        }

        return is_array($data) ? $data : [];
    }

    /**
     * @param array<string, string> $values
     */
    public function setSecrets(array $values): void
    {
        $this->credentials = Crypt::encryptString(json_encode($values));
    }

    public function providerName(): string
    {
        return self::PROVIDERS[$this->provider] ?? $this->provider;
    }
}
