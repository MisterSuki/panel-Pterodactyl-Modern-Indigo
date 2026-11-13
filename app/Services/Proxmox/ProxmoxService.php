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
     * What a host looks like, for the administration: its version, its nodes (with their storage), its machines and
     * its templates. The readings are asked at the same time (two rounds: the host, then the storage of each node), and
     * kept 30 seconds so that coming back to the page does not ask Proxmox again.
     *
     * @return array{ok: bool, error: ?string, version: ?string, nodes: array, vms: array, templates: array}
     */
    public function overview(ProxmoxHost $host, bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget($this->cacheKey($host));
        }

        return Cache::remember($this->cacheKey($host), 30, fn () => $this->read($host));
    }

    /**
     * The overview kept from a recent visit, or null when Proxmox has to be asked (the page then loads it afterwards).
     */
    public function cachedOverview(ProxmoxHost $host): ?array
    {
        $cached = Cache::get($this->cacheKey($host));

        return is_array($cached) ? $cached : null;
    }

    public function forgetOverview(ProxmoxHost $host): void
    {
        Cache::forget($this->cacheKey($host));
    }

    private function read(ProxmoxHost $host): array
    {
        $failed = fn (string $message) => ['ok' => false, 'error' => $message, 'version' => null, 'nodes' => [], 'vms' => [], 'templates' => []];
        $client = $this->client($host);

        try {
            $first = $client->many([
                'version' => '/version',
                'nodes' => '/nodes',
                'resources' => '/cluster/resources?type=vm',
            ]);
        } catch (DisplayException $exception) {
            return $failed($exception->getMessage());
        }
        foreach (['version', 'nodes', 'resources'] as $key) {
            if ($first[$key] instanceof DisplayException) {
                return $failed($first[$key]->getMessage());
            }
        }

        $rawNodes = array_values(array_filter((array) $first['nodes'], 'is_array'));
        $online = array_values(array_filter($rawNodes, fn ($node) => ($node['status'] ?? '') === 'online'));
        $storages = [];
        if ($online) {
            $paths = [];
            foreach ($online as $index => $node) {
                $paths['n' . $index] = '/nodes/' . rawurlencode((string) ($node['node'] ?? '')) . '/storage';
            }
            $answers = $client->many($paths);
            foreach ($online as $index => $node) {
                // A node can refuse the storage list (missing Datastore.Audit): its other figures still show.
                $answer = $answers['n' . $index] ?? null;
                $storages[(string) ($node['node'] ?? '')] = is_array($answer) ? $answer : [];
            }
        }

        $nodes = [];
        foreach ($rawNodes as $node) {
            $name = (string) ($node['node'] ?? '');
            $storage = [];
            foreach ($storages[$name] ?? [] as $store) {
                if (!is_array($store) || empty($store['active']) || (int) ($store['total'] ?? 0) <= 0) {
                    continue;
                }
                $storage[] = [
                    'name' => (string) ($store['storage'] ?? ''),
                    'type' => (string) ($store['type'] ?? ''),
                    'used' => (int) ($store['used'] ?? 0),
                    'total' => (int) ($store['total'] ?? 0),
                ];
            }
            $nodes[] = [
                'name' => $name,
                'online' => ($node['status'] ?? '') === 'online',
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

        $vms = [];
        $templates = [];
        foreach ((array) $first['resources'] as $vm) {
            if (!is_array($vm) || ($vm['type'] ?? '') !== 'qemu') {
                continue;
            }
            if (!empty($vm['template'])) {
                $templates[] = [
                    'vmid' => (int) ($vm['vmid'] ?? 0),
                    'node' => (string) ($vm['node'] ?? ''),
                    'name' => (string) ($vm['name'] ?? ''),
                    'disk' => (int) ($vm['maxdisk'] ?? 0),
                ];
                continue;
            }
            $vms[] = [
                'vmid' => (int) ($vm['vmid'] ?? 0),
                'node' => (string) ($vm['node'] ?? ''),
                'name' => (string) ($vm['name'] ?? ''),
                'status' => (string) ($vm['status'] ?? 'unknown'),
                'cpu' => round((float) ($vm['cpu'] ?? 0) * 100, 1),
                'cores' => (int) ($vm['maxcpu'] ?? 0),
                'memory' => (int) ($vm['mem'] ?? 0),
                'max_memory' => (int) ($vm['maxmem'] ?? 0),
                'max_disk' => (int) ($vm['maxdisk'] ?? 0),
            ];
        }
        usort($vms, fn ($a, $b) => $a['vmid'] <=> $b['vmid']);
        usort($templates, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return ['ok' => true, 'error' => null, 'version' => (string) ($first['version']['version'] ?? '?'), 'nodes' => $nodes, 'vms' => $vms, 'templates' => $templates];
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
     * True while the console relay runs on this machine (it touches <storage>/app/vnc/.alive every 30 seconds).
     */
    public function consoleAvailable(): bool
    {
        $alive = storage_path('app/vnc/.alive');

        return is_file($alive) && (int) @filemtime($alive) > time() - 90;
    }

    /**
     * Opens the console of a VPS for the browser: Proxmox gives a ticket, and a one-time session file tells the relay
     * where to connect and with which token. The browser only gets the id of the session (and the VNC password).
     *
     * @return array{session: string, password: string}
     *
     * @throws DisplayException
     */
    public function openConsole(ProxmoxVm $vm): array
    {
        $directory = storage_path('app/vnc');
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new DisplayException('The console cannot be opened: the folder of its sessions cannot be made.');
        }

        $client = $this->client($vm->host);
        $proxy = $client->vncProxy($vm->node, $vm->vmid);
        $session = bin2hex(random_bytes(24));
        $file = $directory . '/' . $session . '.json';
        $written = @file_put_contents($file, json_encode([
            'url' => $vm->host->url,
            'auth' => $client->authorization(),
            'verify' => $vm->host->verify_tls,
            'node' => $vm->node,
            'vmid' => $vm->vmid,
            'port' => $proxy['port'],
            'ticket' => $proxy['ticket'],
            // Proxmox waits only a few seconds for the connection.
            'expires' => time() + 30,
        ]), LOCK_EX);
        if ($written === false) {
            throw new DisplayException('The console cannot be opened: its session cannot be written.');
        }
        @chmod($file, 0600);

        return ['session' => $session, 'password' => $proxy['ticket']];
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
