<?php

namespace Pterodactyl\Services\Web;

use Illuminate\Support\Str;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;

/**
 * The settings of the web hosting, kept with the other settings of the panel.
 *
 * The token that the web server (Caddy) uses to fetch its configuration is never kept: only its hash is, so it can be
 * checked but not read back. It is shown once, when it is made.
 */
class WebHostingSettings
{
    public function __construct(private SettingsRepositoryInterface $settings)
    {
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $value = $this->settings->get('web:' . $key, null);

        return $value === null || $value === '' ? $default : (string) $value;
    }

    public function set(string $key, ?string $value): void
    {
        $this->settings->set('web:' . $key, $value === '' ? null : $value);
    }

    public function enabled(): bool
    {
        return $this->get('enabled') === '1';
    }

    /**
     * The addresses of the web server. A domain is only served once its DNS leads to one of them. When none is given,
     * this check is off and every domain is served at once (the administration takes the responsibility).
     *
     * @return array<int, string>
     */
    public function serverIps(): array
    {
        $ips = [];
        foreach (preg_split('/[\s,;]+/', (string) $this->get('server_ips', '')) ?: [] as $ip) {
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                $ips[] = $ip;
            }
        }

        return array_values(array_unique($ips));
    }

    /**
     * A domain of the hosting company, under which every site gets a name of its own (site.hosting.example.com). Its DNS
     * has to lead every name under it to the web server (a wildcard record).
     */
    public function baseDomain(): ?string
    {
        $domain = strtolower(trim((string) $this->get('base_domain', ''), " .\t"));

        return preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/', $domain) === 1 ? $domain : null;
    }

    /**
     * Makes a new token, keeps its hash and gives the token itself, this one time.
     */
    public function newToken(): string
    {
        $token = 'wh_' . Str::random(48);
        $this->set('proxy_token_hash', hash('sha256', $token));

        return $token;
    }

    public function hasToken(): bool
    {
        return $this->get('proxy_token_hash') !== null;
    }

    public function tokenIsValid(?string $token): bool
    {
        $hash = $this->get('proxy_token_hash');

        return $hash !== null && $token !== null && $token !== '' && hash_equals($hash, hash('sha256', $token));
    }
}
