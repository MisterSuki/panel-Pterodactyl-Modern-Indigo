<?php

namespace Pterodactyl\Services\Proxmox;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Models\ProxmoxHost;
use Pterodactyl\Models\ProxmoxVm;

/**
 * Proxmox in the panel: the hosts the administration connects (with an API token), what they look like (nodes, storage,
 * machines), and the machines given to users as VPS, which they can see and turn on and off.
 */
class ProxmoxService
{
    public function client(ProxmoxHost $host): ProxmoxClient
    {
        return new ProxmoxClient($host);
    }

    /**
     * Creates or changes a host. The address is kept as "https://host:port" (8006 when no port is given); the secret
     * of the token is encrypted, and left as it is when the field is empty on a change.
     *
     * @throws DisplayException
     */
    public function saveHost(array $data, ?ProxmoxHost $host = null): ProxmoxHost
    {
        $url = $this->normalizeUrl((string) $data['url']);
        $host ??= new ProxmoxHost();
        $host->name = trim((string) $data['name']);
        $host->url = $url;
        $host->token_id = trim((string) $data['token_id']);
        $host->verify_tls = (bool) ($data['verify_tls'] ?? false);

        $secret = trim((string) ($data['token_secret'] ?? ''));
        if ($secret !== '') {
            $host->token_secret = Crypt::encryptString($secret);
        } elseif (!$host->exists) {
            throw new DisplayException('Enter the secret of the API token.');
        }

        $host->save();
        Cache::forget($this->cacheKey($host));

        return $host;
    }

    /**
     * What a host looks like, for the administration: its version, its nodes (with their storage) and its machines.
     * Kept 15 seconds, so that opening the page twice does not ask Proxmox twice.
     *
     * @return array{ok: bool, error: ?string, version: ?string, nodes: array, vms: array, templates: array}
     */
    public function overview(ProxmoxHost $host, bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget($this->cacheKey($host));
        }

        return Cache::remember($this->cacheKey($host), 15, function () use ($host) {
            $client = $this->client($host);
            try {
                $version = $client->version();
                $nodes = [];
                foreach ($client->nodes() as $node) {
                    $name = (string) ($node['node'] ?? '');
                    $online = ($node['status'] ?? '') === 'online';
                    $storage = [];
                    if ($online) {
                        try {
                            foreach ($client->storage($name) as $store) {
                                if (empty($store['active']) || (int) ($store['total'] ?? 0) <= 0) {
                                    continue;
                                }
                                $storage[] = [
                                    'name' => (string) ($store['storage'] ?? ''),
                                    'type' => (string) ($store['type'] ?? ''),
                                    'used' => (int) ($store['used'] ?? 0),
                                    'total' => (int) ($store['total'] ?? 0),
                                ];
                            }
                        } catch (DisplayException) {
                            // A node can refuse the storage list (missing Datastore.Audit): its figures still show.
                        }
                    }
                    $nodes[] = [
                        'name' => $name,
                        'online' => $online,
                        'cpu' => round((float) ($node['cpu'] ?? 0) * 100, 1),
                        'cores' => (int) ($node['maxcpu'] ?? 0),
                        'memory' => (int) ($node['mem'] ?? 0),
                        'max_memory' => (int) ($node['maxmem'] ?? 0),
                        'disk' => (int) ($node['disk'] ?? 0),
                        'max_disk' => (int) ($node['maxdisk'] ?? 0),
                        'uptime' => (int) ($node['uptime'] ?? 0),
                        'storage' => $storage,
                    ];
                }
                usort($nodes, fn ($a, $b) => strcmp($a['name'], $b['name']));

                $vms = array_map(fn (array $vm) => [
                    'vmid' => (int) ($vm['vmid'] ?? 0),
                    'node' => (string) ($vm['node'] ?? ''),
                    'name' => (string) ($vm['name'] ?? ''),
                    'status' => (string) ($vm['status'] ?? 'unknown'),
                    'cpu' => round((float) ($vm['cpu'] ?? 0) * 100, 1),
                    'cores' => (int) ($vm['maxcpu'] ?? 0),
                    'memory' => (int) ($vm['mem'] ?? 0),
                    'max_memory' => (int) ($vm['maxmem'] ?? 0),
                    'max_disk' => (int) ($vm['maxdisk'] ?? 0),
                ], $client->vms());
                usort($vms, fn ($a, $b) => $a['vmid'] <=> $b['vmid']);

                return ['ok' => true, 'error' => null, 'version' => $version, 'nodes' => $nodes, 'vms' => $vms, 'templates' => $client->templates()];
            } catch (DisplayException $exception) {
                return ['ok' => false, 'error' => $exception->getMessage(), 'version' => null, 'nodes' => [], 'vms' => [], 'templates' => []];
            }
        });
    }

    /**
     * The live figures of a set of VPS, asking each host once for all of its machines. A host that does not answer
     * leaves its machines without figures (null).
     *
     * @param Collection<int, ProxmoxVm> $vms
     *
     * @return array<int, array<string, mixed>|null> by id of the VPS
     */
    public function live(Collection $vms): array
    {
        $result = [];
        foreach ($vms->groupBy('host_id') as $group) {
            /** @var ProxmoxVm $first */
            $first = $group->first();
            try {
                $byId = collect($this->client($first->host)->vms())->keyBy(fn ($vm) => (int) ($vm['vmid'] ?? 0));
            } catch (DisplayException) {
                $byId = collect();
            }
            foreach ($group as $vm) {
                $result[$vm->id] = $byId->get($vm->vmid);
            }
        }

        return $result;
    }

    /**
     * A VPS as the user sees it: its name, its state and what it uses, never the numbers of Proxmox.
     */
    public function present(ProxmoxVm $vm, ?array $live): array
    {
        return [
            'uuid' => $vm->uuid,
            'name' => $vm->name,
            // "ready", "creating", "deleting" or "failed": a VPS that is not ready has no figures yet.
            'state' => $vm->isReady() ? 'ready' : (in_array($vm->state, ProxmoxVm::CREATING, true) ? 'creating' : (in_array($vm->state, ProxmoxVm::DELETING, true) ? 'deleting' : 'failed')),
            'status' => $live ? $this->status($live) : 'unknown',
            'cpu' => $live ? round((float) ($live['cpu'] ?? 0) * 100, 1) : null,
            'cores' => $live ? (int) ($live['maxcpu'] ?? $live['cpus'] ?? 0) : null,
            'memory' => $live ? (int) ($live['mem'] ?? 0) : null,
            'max_memory' => $live ? (int) ($live['maxmem'] ?? 0) : null,
            'max_disk' => $live ? (int) ($live['maxdisk'] ?? 0) : null,
            'net_in' => $live ? (int) ($live['netin'] ?? 0) : null,
            'net_out' => $live ? (int) ($live['netout'] ?? 0) : null,
            'uptime' => $live ? (int) ($live['uptime'] ?? 0) : null,
        ];
    }

    /**
     * "running", "stopped", "paused", or "busy" while Proxmox works on it (a backup, a migration...).
     */
    public function status(array $live): string
    {
        if (!empty($live['lock'])) {
            return 'busy';
        }
        $status = (string) ($live['qmpstatus'] ?? $live['status'] ?? 'unknown');

        return in_array($status, ['running', 'stopped', 'paused'], true) ? $status : 'unknown';
    }

    /**
     * @throws DisplayException
     */
    private function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host']) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            throw new DisplayException('The address of Proxmox must look like https://203.0.113.10:8006.');
        }

        return 'https://' . strtolower($parts['host']) . ':' . (int) ($parts['port'] ?? 8006);
    }

    private function cacheKey(ProxmoxHost $host): string
    {
        return 'proxmox:overview:' . $host->id;
    }
}
