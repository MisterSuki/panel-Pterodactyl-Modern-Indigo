<?php

namespace Pterodactyl\Http\Controllers\Api\Client;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Models\ProxmoxVm;
use Pterodactyl\Services\Proxmox\ProxmoxClient;
use Pterodactyl\Services\Proxmox\ProxmoxService;

/**
 * The VPS of the person who is signed in (virtual machines of Proxmox given to them by the administration): the list,
 * one machine with its addresses and its history, and turning it on and off. Nobody sees anyone else's.
 */
class VpsController extends ClientApiController
{
    public function __construct(private ProxmoxService $proxmox)
    {
        parent::__construct();
    }

    public function index(Request $request): JsonResponse
    {
        $vms = ProxmoxVm::query()->with('host')->where('user_id', $request->user()->id)->orderBy('name')->limit(100)->get();
        $live = $vms->isEmpty() ? [] : $this->proxmox->live($vms);

        return new JsonResponse([
            'object' => 'list',
            'data' => $vms->map(fn (ProxmoxVm $vm) => $this->proxmox->present($vm, $live[$vm->id] ?? null))->all(),
        ]);
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        $vm = $this->find($request, $uuid);
        $client = $this->proxmox->client($vm->host);

        try {
            $live = $client->status($vm->node, $vm->vmid);
            $config = $client->config($vm->node, $vm->vmid);
        } catch (DisplayException $exception) {
            return new JsonResponse(['data' => array_merge($this->proxmox->present($vm, null), [
                'error' => $exception->getMessage(),
                'addresses' => [],
                'os' => null,
                'agent' => false,
            ])]);
        }

        $running = $this->proxmox->status($live) === 'running';

        return new JsonResponse(['data' => array_merge($this->proxmox->present($vm, $live), [
            'cores' => (int) ($live['cpus'] ?? 0) ?: (int) ($config['cores'] ?? 1) * (int) ($config['sockets'] ?? 1),
            'error' => null,
            'addresses' => $running && !empty($config['agent']) ? $client->addresses($vm->node, $vm->vmid) : [],
            'os' => isset($config['ostype']) ? (string) $config['ostype'] : null,
            'agent' => !empty($config['agent']),
        ])]);
    }

    /**
     * The last hour, day or week of the machine, as kept by Proxmox: CPU in percent of the machine, memory in bytes,
     * network in bytes per second.
     */
    public function history(Request $request, string $uuid): JsonResponse
    {
        $vm = $this->find($request, $uuid);
        $range = (string) $request->query('range', 'hour');
        if (!in_array($range, ['hour', 'day', 'week'], true)) {
            $range = 'hour';
        }

        try {
            $rows = $this->proxmox->client($vm->host)->history($vm->node, $vm->vmid, $range);
        } catch (DisplayException $exception) {
            return new JsonResponse(['error' => $exception->getMessage(), 'data' => []], Response::HTTP_BAD_GATEWAY);
        }

        $points = [];
        foreach ($rows as $row) {
            if (!isset($row['time'])) {
                continue;
            }
            $points[] = [
                'at' => (int) $row['time'],
                'cpu' => isset($row['cpu']) ? round((float) $row['cpu'] * 100, 2) : null,
                'memory' => isset($row['mem']) ? (int) $row['mem'] : null,
                'net_in' => isset($row['netin']) ? (int) $row['netin'] : null,
                'net_out' => isset($row['netout']) ? (int) $row['netout'] : null,
            ];
        }

        return new JsonResponse(['data' => $points]);
    }

    public function power(Request $request, string $uuid): Response
    {
        $vm = $this->find($request, $uuid);
        $data = $request->validate(['action' => ['required', 'string', Rule::in(ProxmoxClient::ACTIONS)]]);

        $this->proxmox->client($vm->host)->power($vm->node, $vm->vmid, $data['action']);

        Activity::event('user:vps.power')
            ->property(['name' => $vm->name, 'action' => $data['action']])
            ->log();

        return $this->returnNoContent();
    }

    private function find(Request $request, string $uuid): ProxmoxVm
    {
        /** @var ProxmoxVm $vm */
        $vm = ProxmoxVm::query()->with('host')
            ->where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return $vm;
    }
}
