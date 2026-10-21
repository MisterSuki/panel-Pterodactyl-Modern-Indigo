<?php

namespace Pterodactyl\Services\Subdomains;

use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerCustomDomain;
use Pterodactyl\Models\SubdomainDomain;

/**
 * The own domains of the servers. The user creates the records in their own DNS; the panel tells them which ones
 * (pointing at the address of the server) and checks with a DNS lookup that the name really leads to the server.
 */
class CustomDomainService
{
    public const MAX_PER_SERVER = 10;

    public function __construct(private SettingsRepositoryInterface $settings, private SubdomainService $subdomains)
    {
    }

    public function enabled(): bool
    {
        return $this->settings->get('subdomains:user_domains', '0') === '1';
    }

    public function setEnabled(bool $enabled): void
    {
        $this->settings->set('subdomains:user_domains', $enabled ? '1' : '0');
    }

    /**
     * @throws DisplayException
     */
    public function create(Server $server, string $hostname, ?Allocation $allocation, ?string $notes): ServerCustomDomain
    {
        if (!$this->enabled()) {
            throw new DisplayException('Adding your own domains is not allowed on this panel.');
        }
        if (ServerCustomDomain::query()->where('server_id', $server->id)->count() >= self::MAX_PER_SERVER) {
            throw new DisplayException('This server has reached the maximum of own domains.');
        }
        $hostname = $this->checkHostname($hostname);

        $domain = new ServerCustomDomain([
            'server_id' => $server->id,
            'hostname' => $hostname,
            'allocation_id' => $allocation?->id,
            'notes' => $notes !== null ? substr($notes, 0, 191) : null,
        ]);
        $domain->save();
        $this->verify($domain);

        return $domain;
    }

    /**
     * A full name such as play.example.com, not already used, and not under a domain of the panel (those are offered as
     * subdomains instead, so nobody can take a name of the panel).
     *
     * @throws DisplayException
     */
    public function checkHostname(string $hostname, ?int $ignoreId = null): string
    {
        $hostname = strtolower(trim(rtrim(trim($hostname), '.')));
        if (preg_match('/^(?=.{4,191}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $hostname) !== 1) {
            throw new DisplayException('Enter a full domain name, such as play.example.com.');
        }
        $panel = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        if ($panel !== '' && ($hostname === $panel || str_ends_with($panel, '.' . $hostname))) {
            throw new DisplayException('This domain is the one of the panel.');
        }
        foreach (SubdomainDomain::query()->pluck('domain') as $zone) {
            if ($hostname === $zone || str_ends_with($hostname, '.' . $zone)) {
                throw new DisplayException('This domain belongs to the panel: make a subdomain in the list above instead.');
            }
        }
        $taken = ServerCustomDomain::query()->where('hostname', $hostname)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists();
        if ($taken) {
            throw new DisplayException('This domain is already used on the panel.');
        }

        return $hostname;
    }

    /**
     * The records the user must create in their DNS for the name to lead to the server: the address (A, AAAA or
     * CNAME) and, when the administration makes SRV records for its subdomains, the same SRV records.
     *
     * @return array<int, array{type: string, name: string, content: string}>
     */
    public function records(ServerCustomDomain $domain): array
    {
        $allocation = $domain->allocation;
        if (!$allocation) {
            return [];
        }
        try {
            [$type, $address] = $this->subdomains->address($allocation);
        } catch (DisplayException) {
            return [];
        }

        $records = [['type' => $type, 'name' => $domain->hostname, 'content' => $address]];
        foreach ($this->subdomains->templates() as $template) {
            if ($template['type'] !== 'SRV') {
                continue;
            }
            $records[] = [
                'type' => 'SRV',
                'name' => $template['service'] . '.' . $template['protocol'] . '.' . $domain->hostname,
                'content' => sprintf('%d %d %d %s', $template['priority'], $template['weight'], $allocation->port, $domain->hostname),
            ];
        }

        return $records;
    }

    /**
     * Looks the name up in the DNS and keeps whether it leads to the address of the server.
     */
    public function verify(ServerCustomDomain $domain): bool
    {
        $ok = false;
        $records = $this->records($domain);
        if (!empty($records)) {
            ['type' => $type, 'content' => $expected] = $records[0];
            $found = $this->addressesOf($domain->hostname);
            $wanted = $type === 'CNAME' ? $this->addressesOf($expected) : [$expected];
            $ok = !empty($found) && !empty(array_intersect(array_map('strtolower', $found), array_map('strtolower', $wanted)));
        }
        $domain->forceFill(['verified_at' => $ok ? now() : null])->save();

        return $ok;
    }

    /**
     * The IP addresses a name leads to (following CNAMEs), IPv4 and IPv6.
     *
     * @return string[]
     */
    private function addressesOf(string $hostname): array
    {
        $ips = @gethostbynamel($hostname) ?: [];
        foreach (@dns_get_record($hostname, DNS_AAAA) ?: [] as $record) {
            if (!empty($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($ips));
    }
}
