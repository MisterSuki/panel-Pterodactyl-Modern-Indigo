<?php

namespace Pterodactyl\Services\Subdomains;

use Illuminate\Http\Client\ConnectionException;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerSubdomain;
use Pterodactyl\Models\SubdomainDomain;

/**
 * Subdomains of the servers: name rules, how many a server may have, and the DNS records made for each one from the
 * templates the administration set ({name}, {domain}, {fqdn}, {ip}, {port}, {server}, {server_name}).
 */
class SubdomainService
{
    public const DEFAULT_BLACKLIST = "^www$\n^mail$\n^admin$\n^panel$";

    public const DEFAULT_RECORDS = [
        ['type' => 'address', 'name' => '{name}', 'ttl' => 0, 'proxied' => false],
        ['type' => 'SRV', 'service' => '_minecraft', 'protocol' => '_tcp', 'name' => '{name}', 'ttl' => 0, 'priority' => 0, 'weight' => 5, 'port' => '{port}', 'target' => '{fqdn}'],
    ];

    public function __construct(private SettingsRepositoryInterface $settings)
    {
    }

    // ---- Settings -------------------------------------------------------------------------------------------------

    public function blacklist(): string
    {
        return (string) $this->settings->get('subdomains:blacklist', self::DEFAULT_BLACKLIST);
    }

    public function templates(): array
    {
        $raw = json_decode((string) $this->settings->get('subdomains:records', ''), true);

        return is_array($raw) ? $this->cleanTemplates($raw) : self::DEFAULT_RECORDS;
    }

    public function defaultLimit(): int
    {
        return max(0, (int) $this->settings->get('subdomains:default_limit', '0'));
    }

    public function saveSettings(string $blacklist, array $templates, int $defaultLimit): void
    {
        $lines = array_filter(array_map('trim', preg_split('/\R/', $blacklist) ?: []), fn ($l) => $l !== '');
        $this->settings->set('subdomains:blacklist', implode("\n", array_slice($lines, 0, 200)));
        $this->settings->set('subdomains:records', json_encode($this->cleanTemplates($templates)));
        $this->settings->set('subdomains:default_limit', (string) max(0, min(100, $defaultLimit)));
    }

    public function cleanTemplates(array $raw): array
    {
        $clean = [];
        foreach (array_slice($raw, 0, 10) as $row) {
            if (!is_array($row) || !in_array($row['type'] ?? '', ['address', 'CNAME', 'TXT', 'SRV'], true)) {
                continue;
            }
            $item = [
                'type' => $row['type'],
                'name' => substr(trim((string) ($row['name'] ?? '{name}')), 0, 120) ?: '{name}',
                'ttl' => max(0, min(86400, (int) ($row['ttl'] ?? 0))),
            ];
            if (in_array($row['type'], ['address', 'CNAME'], true)) {
                $item['proxied'] = filter_var($row['proxied'] ?? false, FILTER_VALIDATE_BOOLEAN);
            }
            if (in_array($row['type'], ['CNAME', 'TXT'], true)) {
                $item['content'] = substr(trim((string) ($row['content'] ?? '')), 0, 255);
            }
            if ($row['type'] === 'SRV') {
                $item['service'] = '_' . ltrim(preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($row['service'] ?? '_minecraft')) ?: 'minecraft', '_');
                $item['protocol'] = in_array($row['protocol'] ?? '_tcp', ['_tcp', '_udp', '_tls'], true) ? $row['protocol'] : '_tcp';
                $item['priority'] = max(0, min(65535, (int) ($row['priority'] ?? 0)));
                $item['weight'] = max(0, min(65535, (int) ($row['weight'] ?? 5)));
                $item['port'] = substr(trim((string) ($row['port'] ?? '{port}')), 0, 20) ?: '{port}';
                $item['target'] = substr(trim((string) ($row['target'] ?? '{fqdn}')), 0, 120) ?: '{fqdn}';
            }
            $clean[] = $item;
        }

        return $clean;
    }

    // ---- Limits and names -----------------------------------------------------------------------------------------

    public function limitFor(Server $server): int
    {
        $own = $server->getAttribute('subdomain_limit');

        return $own === null ? $this->defaultLimit() : max(0, (int) $own);
    }

    /**
     * @throws DisplayException
     */
    public function checkName(string $name, SubdomainDomain $domain, ?int $ignoreId = null): void
    {
        if (preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $name) !== 1) {
            throw new DisplayException('The name can only have lowercase letters, numbers and dashes (not at the start or the end).');
        }
        foreach (preg_split('/\R/', $this->blacklist()) ?: [] as $rule) {
            $rule = trim($rule);
            if ($rule === '') {
                continue;
            }
            $pattern = '/' . str_replace('/', '\/', $rule) . '/i';
            if (@preg_match($pattern, $name) === 1) {
                throw new DisplayException('This name is not allowed.');
            }
        }
        $taken = ServerSubdomain::query()->where('domain_id', $domain->id)->where('name', $name)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists();
        if ($taken) {
            throw new DisplayException('This subdomain is already taken.');
        }
    }

    // ---- Subdomains -----------------------------------------------------------------------------------------------

    /**
     * @throws DisplayException
     */
    public function create(Server $server, SubdomainDomain $domain, string $name, ?Allocation $allocation, ?string $notes): ServerSubdomain
    {
        if (!$domain->available) {
            throw new DisplayException('This domain is not available.');
        }
        $limit = $this->limitFor($server);
        if (ServerSubdomain::query()->where('server_id', $server->id)->count() >= $limit) {
            throw new DisplayException($limit === 0 ? 'This server cannot have subdomains.' : 'This server has reached its limit of subdomains.');
        }
        $name = strtolower(trim($name));
        $this->checkName($name, $domain);

        $subdomain = new ServerSubdomain([
            'server_id' => $server->id,
            'domain_id' => $domain->id,
            'name' => $name,
            'allocation_id' => $allocation?->id,
            'notes' => $notes !== null ? substr($notes, 0, 191) : null,
        ]);
        $subdomain->setRelation('domain', $domain);
        $subdomain->setRelation('server', $server);
        $subdomain->records = $allocation ? $this->publish($subdomain, $allocation) : [];
        $subdomain->save();

        return $subdomain;
    }

    /**
     * Points the subdomain at another allocation (or at none): the old records are removed, the new ones made.
     *
     * @throws DisplayException
     */
    public function reassign(ServerSubdomain $subdomain, ?Allocation $allocation): void
    {
        $this->unpublish($subdomain);
        $subdomain->records = [];
        $subdomain->allocation_id = $allocation?->id;
        $subdomain->save();
        if ($allocation) {
            $subdomain->records = $this->publish($subdomain, $allocation);
            $subdomain->save();
        }
    }

    /**
     * @throws DisplayException
     */
    public function delete(ServerSubdomain $subdomain, bool $force = false): void
    {
        try {
            $this->unpublish($subdomain);
        } catch (DisplayException $exception) {
            if (!$force) {
                throw $exception;
            }
        }
        $subdomain->delete();
    }

    /**
     * Makes the records of a subdomain from the templates. If one fails, the ones already made are removed.
     *
     * @throws DisplayException
     */
    private function publish(ServerSubdomain $subdomain, Allocation $allocation): array
    {
        $client = new DnsClient($subdomain->domain);
        $made = [];
        try {
            foreach ($this->build($subdomain, $allocation) as $record) {
                $made[] = $client->create($record);
            }
        } catch (DisplayException|ConnectionException $exception) {
            foreach ($made as $record) {
                try {
                    $client->delete($record);
                } catch (\Throwable) {
                    // best effort
                }
            }
            throw $exception instanceof DisplayException ? $exception : new DisplayException('The DNS provider could not be reached.');
        }

        return $made;
    }

    /**
     * @throws DisplayException
     */
    private function unpublish(ServerSubdomain $subdomain): void
    {
        $records = $subdomain->records ?? [];
        if (empty($records)) {
            return;
        }
        $client = new DnsClient($subdomain->domain);
        try {
            foreach ($records as $record) {
                $client->delete($record);
            }
        } catch (ConnectionException) {
            throw new DisplayException('The DNS provider could not be reached.');
        }
    }

    /**
     * The records for a subdomain pointed at an allocation, from the templates.
     *
     * @throws DisplayException
     */
    public function build(ServerSubdomain $subdomain, Allocation $allocation): array
    {
        $domain = $subdomain->domain->domain;
        $fqdn = $subdomain->name . '.' . $domain;
        [$addressType, $address] = $this->address($allocation);
        $server = $subdomain->server;
        $vars = [
            '{name}' => $subdomain->name,
            '{domain}' => $domain,
            '{fqdn}' => $fqdn,
            '{ip}' => $address,
            '{port}' => (string) $allocation->port,
            '{server}' => (string) ($server->uuidShort ?? ''),
            '{server_name}' => preg_replace('/[^a-z0-9-]/', '-', strtolower((string) ($server->name ?? ''))),
        ];
        $fill = fn (string $value) => strtr($value, $vars);
        $absolute = function (string $relative) use ($domain) {
            $relative = trim($relative, '.');

            return ($relative === '' || $relative === '@') ? $domain : (str_ends_with($relative, '.' . $domain) || $relative === $domain ? $relative : $relative . '.' . $domain);
        };

        $records = [];
        foreach ($this->templates() as $template) {
            $name = $absolute($fill($template['name']));
            switch ($template['type']) {
                case 'address':
                    $records[] = ['type' => $addressType, 'name' => $name, 'content' => $address, 'ttl' => $template['ttl'], 'proxied' => $template['proxied'] ?? false];
                    break;
                case 'CNAME':
                case 'TXT':
                    $records[] = ['type' => $template['type'], 'name' => $name, 'content' => $fill($template['content'] ?? ''), 'ttl' => $template['ttl'], 'proxied' => $template['proxied'] ?? false];
                    break;
                case 'SRV':
                    $records[] = [
                        'type' => 'SRV',
                        'name' => $template['service'] . '.' . $template['protocol'] . '.' . $name,
                        'ttl' => $template['ttl'],
                        'priority' => $template['priority'],
                        'weight' => $template['weight'],
                        'port' => (int) $fill($template['port']),
                        'target' => $absolute($fill($template['target'])),
                    ];
                    break;
            }
        }

        return $records;
    }

    /**
     * Where the address record points: the public IP of the allocation (A or AAAA), or else a name (CNAME): the alias
     * of the allocation, or the address of its node.
     *
     * @return array{0: string, 1: string}
     *
     * @throws DisplayException
     */
    public function address(Allocation $allocation): array
    {
        $public = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
        foreach ([$allocation->ip_alias, $allocation->ip, $allocation->node?->fqdn] as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate === '') {
                continue;
            }
            if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | $public)) {
                return ['A', $candidate];
            }
            if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | $public)) {
                return ['AAAA', $candidate];
            }
            if (!filter_var($candidate, FILTER_VALIDATE_IP) && preg_match('/^(?=.{1,253}$)([a-z0-9-]+\.)+[a-z]{2,}$/i', $candidate)) {
                return ['CNAME', strtolower($candidate)];
            }
        }

        throw new DisplayException('This allocation has no public address (give it an alias, or a domain name to its node).');
    }
}
