<?php

namespace Pterodactyl\Services\Subdomains;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Models\SubdomainDomain;

/**
 * Talks to the DNS provider of a domain: Cloudflare, Bunny.net DNS or PowerDNS.
 *
 * A record is an array: type (A, AAAA, CNAME, TXT or SRV), name (the full name, without the final dot), ttl (0 = the
 * provider's automatic value), and content (A, AAAA, CNAME, TXT) or priority, weight, port and target (SRV); proxied
 * for Cloudflare. create() gives the record back with the "id" of the provider, which delete() needs.
 */
class DnsClient
{
    private const BUNNY_TYPES = ['A' => 0, 'AAAA' => 1, 'CNAME' => 2, 'TXT' => 3, 'SRV' => 8];

    public function __construct(private SubdomainDomain $domain)
    {
    }

    /**
     * Checks that the keys work and that the zone of the domain is there. Throws with a readable message otherwise.
     *
     * @throws DisplayException
     */
    public function test(): void
    {
        $this->zone(true);
    }

    /**
     * @throws DisplayException
     */
    public function create(array $record): array
    {
        $zone = $this->zone();

        switch ($this->domain->provider) {
            case 'cloudflare':
                $body = ['type' => $record['type'], 'name' => $record['name'], 'ttl' => $record['ttl'] > 0 ? $record['ttl'] : 1];
                if ($record['type'] === 'SRV') {
                    $body['data'] = [
                        'priority' => (int) $record['priority'],
                        'weight' => (int) $record['weight'],
                        'port' => (int) $record['port'],
                        'target' => $record['target'],
                    ];
                } else {
                    $body['content'] = $record['content'];
                    if (in_array($record['type'], ['A', 'AAAA', 'CNAME'], true)) {
                        $body['proxied'] = (bool) ($record['proxied'] ?? false);
                    }
                }
                $response = $this->cloudflare()->post("zones/$zone/dns_records", $body);
                $this->ensure($response, 'create the record ' . $record['name']);
                $record['id'] = (string) $response->json('result.id');

                return $record;

            case 'bunny':
                $relative = $this->relative($record['name']);
                $body = [
                    'Type' => self::BUNNY_TYPES[$record['type']],
                    'Name' => $relative,
                    'Ttl' => $record['ttl'] > 0 ? $record['ttl'] : 300,
                    'Value' => $record['type'] === 'SRV' ? $record['target'] : $record['content'],
                ];
                if ($record['type'] === 'SRV') {
                    $body += ['Priority' => (int) $record['priority'], 'Weight' => (int) $record['weight'], 'Port' => (int) $record['port']];
                }
                $response = $this->bunny()->put("dnszone/$zone/records", $body);
                $this->ensure($response, 'create the record ' . $record['name']);
                $record['id'] = (string) $response->json('Id');

                return $record;

            case 'powerdns':
                $response = $this->powerdns()->patch($this->powerdnsZonePath($zone), ['rrsets' => [[
                    'name' => $record['name'] . '.',
                    'type' => $record['type'],
                    'ttl' => $record['ttl'] > 0 ? $record['ttl'] : 3600,
                    'changetype' => 'REPLACE',
                    'records' => [['content' => $this->powerdnsContent($record), 'disabled' => false]],
                ]]]);
                $this->ensure($response, 'create the record ' . $record['name']);
                $record['id'] = null;

                return $record;
        }

        throw new DisplayException('Unknown DNS provider.');
    }

    /**
     * Removes a record. A record that is already gone is not an error.
     *
     * @throws DisplayException
     */
    public function delete(array $record): void
    {
        $zone = $this->zone();

        switch ($this->domain->provider) {
            case 'cloudflare':
                if (empty($record['id'])) {
                    return;
                }
                $response = $this->cloudflare()->delete("zones/$zone/dns_records/{$record['id']}");
                break;
            case 'bunny':
                if (empty($record['id'])) {
                    return;
                }
                $response = $this->bunny()->delete("dnszone/$zone/records/{$record['id']}");
                break;
            case 'powerdns':
                $response = $this->powerdns()->patch($this->powerdnsZonePath($zone), ['rrsets' => [[
                    'name' => $record['name'] . '.',
                    'type' => $record['type'],
                    'changetype' => 'DELETE',
                ]]]);
                break;
            default:
                return;
        }

        if ($response->status() !== 404) {
            $this->ensure($response, 'delete the record ' . $record['name']);
        }
    }

    /**
     * The id of the zone at the provider, looked up once and kept on the domain.
     *
     * @throws DisplayException
     */
    private function zone(bool $fresh = false): string
    {
        if (!$fresh && $this->domain->zone_id) {
            return $this->domain->zone_id;
        }
        $name = $this->domain->domain;

        switch ($this->domain->provider) {
            case 'cloudflare':
                $response = $this->cloudflare()->get('zones', ['name' => $name]);
                $this->ensure($response, 'find the zone');
                $id = (string) $response->json('result.0.id', '');
                break;
            case 'bunny':
                $response = $this->bunny()->get('dnszone', ['search' => $name, 'perPage' => 1000]);
                $this->ensure($response, 'find the zone');
                $id = '';
                foreach ((array) $response->json('Items', []) as $zone) {
                    if (strtolower((string) ($zone['Domain'] ?? '')) === strtolower($name)) {
                        $id = (string) $zone['Id'];
                    }
                }
                break;
            case 'powerdns':
                $response = $this->powerdns()->get($this->powerdnsZonePath($name . '.'));
                $this->ensure($response, 'find the zone');
                $id = (string) $response->json('id', '');
                break;
            default:
                throw new DisplayException('Unknown DNS provider.');
        }

        if ($id === '') {
            throw new DisplayException("The zone $name was not found at " . $this->domain->providerName() . '.');
        }
        if ($this->domain->exists && $this->domain->zone_id !== $id) {
            $this->domain->forceFill(['zone_id' => $id])->saveQuietly();
        }

        return $id;
    }

    private function cloudflare(): PendingRequest
    {
        return Http::baseUrl('https://api.cloudflare.com/client/v4/')
            ->withToken((string) ($this->domain->secrets()['token'] ?? ''))
            ->acceptJson()->connectTimeout(5)->timeout(15);
    }

    private function bunny(): PendingRequest
    {
        return Http::baseUrl('https://api.bunny.net/')
            ->withHeaders(['AccessKey' => (string) ($this->domain->secrets()['key'] ?? '')])
            ->acceptJson()->connectTimeout(5)->timeout(15);
    }

    private function powerdns(): PendingRequest
    {
        $secrets = $this->domain->secrets();

        return Http::baseUrl(rtrim((string) ($secrets['url'] ?? ''), '/') . '/api/v1/')
            ->withHeaders(['X-API-Key' => (string) ($secrets['key'] ?? '')])
            ->acceptJson()->connectTimeout(5)->timeout(15);
    }

    private function powerdnsZonePath(string $zone): string
    {
        $server = (string) ($this->domain->secrets()['server'] ?? 'localhost') ?: 'localhost';

        return 'servers/' . rawurlencode($server) . '/zones/' . rawurlencode($zone);
    }

    private function powerdnsContent(array $record): string
    {
        return match ($record['type']) {
            'CNAME' => rtrim($record['content'], '.') . '.',
            'TXT' => '"' . str_replace('"', '\"', $record['content']) . '"',
            'SRV' => sprintf('%d %d %d %s.', $record['priority'], $record['weight'], $record['port'], rtrim($record['target'], '.')),
            default => $record['content'],
        };
    }

    // The name of a record relative to the zone ("" for the zone itself).
    private function relative(string $name): string
    {
        $suffix = '.' . $this->domain->domain;

        return str_ends_with($name, $suffix) ? substr($name, 0, -strlen($suffix)) : ($name === $this->domain->domain ? '' : $name);
    }

    /**
     * @throws DisplayException
     */
    private function ensure(Response $response, string $what): void
    {
        if ($response->successful()) {
            return;
        }
        $detail = (string) ($response->json('errors.0.message') ?? $response->json('Message') ?? $response->json('error') ?? '');
        $provider = $this->domain->providerName();

        throw new DisplayException(trim("$provider could not $what (HTTP {$response->status()}). $detail"));
    }
}
