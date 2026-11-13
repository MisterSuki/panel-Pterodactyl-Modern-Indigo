<?php

namespace Pterodactyl\Http\Controllers\Api\Client;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Crypt;
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

        // Being created or deleted: nothing to ask Proxmox yet.
        if (!$vm->isReady()) {
            return new JsonResponse(['data' => array_merge($this->proxmox->present($vm, null), [
                'error' => null,
                'addresses' => $vm->ip ? [$vm->ip->address] : [],
                'os' => null,
                'agent' => false,
                'root_password' => null,
            ])]);
        }

        try {
            // Both asked at the same time.
            $path = '/nodes/' . rawurlencode($vm->node) . '/qemu/' . $vm->vmid;
            $answers = $client->many(['status' => $path . '/status/current', 'config' => $path . '/config']);
            foreach ($answers as $answer) {
                if ($answer instanceof DisplayException) {
                    throw $answer;
                }
            }
            $live = (array) $answers['status'];
            $config = (array) $answers['config'];
        } catch (DisplayException $exception) {
            return new JsonResponse(['data' => array_merge($this->proxmox->present($vm, null), [
                'error' => $exception->getMessage(),
                'addresses' => [],
                'os' => null,
                'agent' => false,
                'root_password' => $this->password($vm),
            ])]);
        }

        $running = $this->proxmox->status($live) === 'running';

        return new JsonResponse(['data' => array_merge($this->proxmox->present($vm, $live), [
            'cores' => (int) ($live['cpus'] ?? 0) ?: (int) ($config['cores'] ?? 1) * (int) ($config['sockets'] ?? 1),
            'error' => null,
            'addresses' => $this->addresses($vm, $running && !empty($config['agent']) ? $client->addresses($vm->node, $vm->vmid) : []),
            'root_password' => $this->password($vm),
            'os' => isset($config['ostype']) ? (string) $config['ostype'] : null,
            'agent' => !empty($config['agent']),
            'console' => $this->proxmox->consoleAvailable(),
        ])]);
    }

    /**
     * The last hour, day or week of the machine, as kept by Proxmox: CPU in percent of the machine, memory in bytes,
     * network in bytes per second.
     */
    public function history(Request $request, string $uuid): JsonResponse
    {
        $vm = $this->find($request, $uuid);
        if (!$vm->isReady()) {
            return new JsonResponse(['data' => []]);
        }
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
        if (!$vm->isReady()) {
            throw new DisplayException('The VPS is not ready yet.');
        }

        $this->proxmox->client($vm->host)->power($vm->node, $vm->vmid, $data['action']);

        Activity::event('user:vps.power')
            ->property(['name' => $vm->name, 'action' => $data['action']])
            ->log();

        return $this->returnNoContent();
    }

    /**
     * Opens the console (noVNC) of the machine. The browser gets the address of the relay of the panel and the VNC
     * password; the token of Proxmox stays on the server.
     */
    public function console(Request $request, string $uuid): JsonResponse
    {
        $vm = $this->find($request, $uuid);
        if (!$vm->isReady()) {
            throw new DisplayException('The VPS is not ready yet.');
        }
        if (!$this->proxmox->consoleAvailable()) {
            throw new DisplayException('The console is not set up on the panel yet: the administrator has to run the update of the panel.');
        }

        $console = $this->proxmox->openConsole($vm);

        Activity::event('user:vps.console')
            ->property(['name' => $vm->name])
            ->log();

        return new JsonResponse(['data' => [
            'path' => '/vnc-relay/' . $console['session'],
            'password' => $console['password'],
        ]]);
    }

    /**
     * The root password set when the panel created the machine (null for a machine made on Proxmox). It is the one of
     * the first start: the owner may have changed it since.
     */
    private function password(ProxmoxVm $vm): ?string
    {
        if (!$vm->password) {
            return null;
        }
        try {
            return Crypt::decryptString($vm->password);
        } catch (DecryptException) {
            return null;
        }
    }

    /**
     * The address given by the panel first, then the ones the guest agent tells.
     *
     * @param array<int, string> $reported
     *
     * @return array<int, string>
     */
    private function addresses(ProxmoxVm $vm, array $reported): array
    {
        return array_values(array_unique(array_merge($vm->ip ? [$vm->ip->address] : [], $reported)));
    }

    private function find(Request $request, string $uuid): ProxmoxVm
    {
        /** @var ProxmoxVm $vm */
        $vm = ProxmoxVm::query()->with(['host', 'ip'])
            ->where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return $vm;
    }
}
