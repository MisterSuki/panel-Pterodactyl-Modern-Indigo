<?php

namespace Pterodactyl\Services\Proxmox;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Models\ProxmoxHost;

/**
 * Talks to the API of one Proxmox host with its API token ("PVEAPIToken=user@realm!id=secret"). Only what the panel
 * needs: the nodes and their storage, the virtual machines, their state and history, and turning them on and off.
 * Every failure comes out as a DisplayException with a sentence that can be shown as it is.
 */
class ProxmoxClient
{
    public const ACTIONS = ['start', 'shutdown', 'reboot', 'stop'];

    public function __construct(private ProxmoxHost $host)
    {
    }

    /**
     * The version of Proxmox VE, which also proves that the address and the token work.
     */
    public function version(): string
    {
        $data = $this->get('/version');

        return (string) ($data['version'] ?? '?');
    }

    /**
     * The nodes of the host: state, CPU (0 to 1), memory and root disk in bytes, uptime in seconds.
     *
     * @return array<int, array<string, mixed>>
     */
    public function nodes(): array
    {
        return array_values(array_filter((array) $this->get('/nodes'), 'is_array'));
    }

    /**
     * The storage of a node that is turned on, with what is used of it.
     *
     * @return array<int, array<string, mixed>>
     */
    public function storage(string $node): array
    {
        return array_values(array_filter((array) $this->get('/nodes/' . rawurlencode($node) . '/storage'), 'is_array'));
    }

    /**
     * Every virtual machine (KVM) of the host, templates left out, with its live figures.
     *
     * @return array<int, array<string, mixed>>
     */
    public function vms(): array
    {
        $all = (array) $this->get('/cluster/resources', ['type' => 'vm']);

        return array_values(array_filter($all, fn ($vm) => is_array($vm)
            && ($vm['type'] ?? '') === 'qemu'
            && empty($vm['template'])));
    }

    /**
     * @return array<string, mixed>
     */
    public function status(string $node, int $vmid): array
    {
        return (array) $this->get($this->vmPath($node, $vmid) . '/status/current');
    }

    /**
     * @return array<string, mixed>
     */
    public function config(string $node, int $vmid): array
    {
        return (array) $this->get($this->vmPath($node, $vmid) . '/config');
    }

    /**
     * The history of the machine kept by Proxmox ("hour", "day", "week"): CPU, memory, network and disk over time.
     *
     * @return array<int, array<string, mixed>>
     */
    public function history(string $node, int $vmid, string $timeframe = 'hour'): array
    {
        $rows = (array) $this->get($this->vmPath($node, $vmid) . '/rrddata', ['timeframe' => $timeframe, 'cf' => 'AVERAGE']);

        return array_values(array_filter($rows, 'is_array'));
    }

    /**
     * The addresses the machine reports through the QEMU guest agent (loopback and link-local left out). Empty when the
     * agent is not installed or not running: that is not an error.
     *
     * @return array<int, string>
     */
    public function addresses(string $node, int $vmid): array
    {
        try {
            $data = (array) $this->get($this->vmPath($node, $vmid) . '/agent/network-get-interfaces', [], 4);
        } catch (DisplayException) {
            return [];
        }

        $found = [];
        foreach ((array) ($data['result'] ?? []) as $interface) {
            if (!is_array($interface) || ($interface['name'] ?? '') === 'lo') {
                continue;
            }
            foreach ((array) ($interface['ip-addresses'] ?? []) as $address) {
                $ip = is_array($address) ? (string) ($address['ip-address'] ?? '') : '';
                if ($ip === '' || str_starts_with($ip, '127.') || str_starts_with(strtolower($ip), 'fe80') || $ip === '::1') {
                    continue;
                }
                $found[] = $ip;
            }
        }

        return array_values(array_unique($found));
    }

    /**
     * Starts, shuts down (cleanly, through ACPI), restarts or stops (pulls the plug) the machine. Proxmox answers with the
     * id of the task it started.
     *
     * @throws DisplayException
     */
    public function power(string $node, int $vmid, string $action): string
    {
        if (!in_array($action, self::ACTIONS, true)) {
            throw new DisplayException('This action does not exist.');
        }

        return (string) $this->send('post', $this->vmPath($node, $vmid) . '/status/' . $action);
    }

    private function vmPath(string $node, int $vmid): string
    {
        return '/nodes/' . rawurlencode($node) . '/qemu/' . $vmid;
    }

    /**
     * @throws DisplayException
     */
    private function get(string $path, array $query = [], int $timeout = 8): mixed
    {
        return $this->send('get', $path, $query, $timeout);
    }

    /**
     * @throws DisplayException
     */
    private function send(string $method, string $path, array $data = [], int $timeout = 8): mixed
    {
        try {
            $request = $this->request($timeout);
            // Proxmox reads the parameters of a change as a form.
            $response = $method === 'post'
                ? $request->asForm()->post($this->base() . $path, $data)
                : $request->get($this->base() . $path, $data);
        } catch (ConnectionException) {
            throw new DisplayException('Proxmox does not answer: check the address and that port 8006 is reachable from the panel.');
        }

        if ($response->status() === 401) {
            throw new DisplayException('Proxmox refused the API token: check its ID and its secret.');
        }
        if ($response->status() === 403) {
            throw new DisplayException('The API token is missing a permission on Proxmox (VM.Audit, VM.PowerMgmt, Sys.Audit, Datastore.Audit).');
        }
        if ($response->failed()) {
            $message = trim((string) ($response->json('message') ?? ''));

            throw new DisplayException('Proxmox answered with an error' . ($message !== '' ? ': ' . mb_substr($message, 0, 200) : ' (' . $response->status() . ').'));
        }

        return $response->json('data');
    }

    private function request(int $timeout): PendingRequest
    {
        try {
            $secret = Crypt::decryptString($this->host->token_secret);
        } catch (DecryptException) {
            throw new DisplayException('The secret of the API token cannot be read: enter it again.');
        }

        return Http::withHeaders([
            'Authorization' => 'PVEAPIToken=' . $this->host->token_id . '=' . $secret,
            'Accept' => 'application/json',
        ])
            ->withOptions(['verify' => $this->host->verify_tls])
            ->connectTimeout(4)
            ->timeout($timeout);
    }

    private function base(): string
    {
        return rtrim($this->host->url, '/') . '/api2/json';
    }
}
