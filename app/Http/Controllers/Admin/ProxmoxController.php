<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\ProxmoxHost;
use Pterodactyl\Models\ProxmoxIp;
use Pterodactyl\Models\ProxmoxVm;
use Pterodactyl\Models\User;
use Pterodactyl\Services\Proxmox\ProxmoxProvisioner;
use Pterodactyl\Services\Proxmox\ProxmoxService;

/**
 * Proxmox seen from the administration: the hosts the panel is connected to (address and API token), the state of
 * their nodes and storage, and their virtual machines, which can be given to users as VPS. It needs the right to manage
 * the settings of the panel.
 */
class ProxmoxController extends Controller
{
    public function __construct(
        private ProxmoxService $proxmox,
        private ProxmoxProvisioner $provisioner,
        private AlertsMessageBag $alert,
    )
    {
    }

    /**
     * The page opens at once with what is already known; the hosts that were not asked recently fill their box right
     * after, each one on its own (see panel()).
     */
    public function index(Request $request): View
    {
        $hosts = ProxmoxHost::query()->orderBy('name')->get();
        if ($request->boolean('refresh')) {
            $hosts->each(fn (ProxmoxHost $host) => $this->proxmox->forgetOverview($host));
        }

        return view('admin.proxmox', [
            'hosts' => $hosts->map(fn (ProxmoxHost $host) => $this->entry($host, $this->proxmox->cachedOverview($host))),
            'editing' => $request->filled('edit') ? $hosts->firstWhere('id', (int) $request->query('edit')) : null,
        ]);
    }

    /**
     * The box of one host, asked by the page after it opened.
     */
    public function panel(ProxmoxHost $host): Response
    {
        return response(view('admin.proxmox.host', ['entry' => $this->entry($host, $this->proxmox->overview($host))])->render());
    }

    /**
     * What the box of a host shows: its overview (null while it was not asked), and the VPS and addresses of the panel.
     */
    private function entry(ProxmoxHost $host, ?array $overview): array
    {
        $panelVms = ProxmoxVm::query()->where('host_id', $host->id)->with(['user:id,username,email', 'ip'])->orderBy('name')->get();

        return [
            'host' => $host,
            'overview' => $overview,
            'assigned' => $panelVms->keyBy('vmid'),
            'panelVms' => $panelVms,
            'ips' => ProxmoxIp::query()->where('host_id', $host->id)->with('vm:id,name')->orderBy('address')->get(),
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->save($request, null);
    }

    public function update(Request $request, ProxmoxHost $host): RedirectResponse
    {
        return $this->save($request, $host);
    }

    public function delete(ProxmoxHost $host): RedirectResponse
    {
        $host->vms()->delete();
        $host->delete();
        $this->alert->success('The Proxmox host was removed. Its VPS are no longer shown to their users.')->flash();

        return redirect()->route('admin.proxmox');
    }

    /**
     * Asks Proxmox for its version, to prove that the address and the token work.
     */
    public function test(ProxmoxHost $host): RedirectResponse
    {
        try {
            $version = $this->proxmox->client($host)->version();
            $this->proxmox->overview($host, true);
            $this->alert->success('Connected to Proxmox VE ' . $version . '.')->flash();
        } catch (DisplayException $exception) {
            $this->alert->danger($exception->getMessage())->flash();
        }

        return redirect()->route('admin.proxmox');
    }

    /**
     * Gives a machine of the host to a user (found by email or username), who then sees it as a VPS.
     */
    public function assign(Request $request, ProxmoxHost $host): RedirectResponse
    {
        $data = $request->validate([
            'vmid' => ['required', 'integer', 'min:100', 'max:999999999'],
            'node' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/'],
            'user' => ['required', 'string', 'max:191'],
            'name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::query()->where('email', $data['user'])->orWhere('username', $data['user'])->first();
        if (!$user) {
            $this->alert->danger('No user has this email or username.')->flash();

            return redirect()->route('admin.proxmox');
        }

        $machine = collect($this->proxmox->overview($host, true)['vms'])->firstWhere('vmid', (int) $data['vmid']);
        if (!$machine) {
            $this->alert->danger('This machine was not found on the Proxmox host.')->flash();

            return redirect()->route('admin.proxmox');
        }

        ProxmoxVm::query()->updateOrCreate(
            ['host_id' => $host->id, 'vmid' => (int) $data['vmid']],
            [
                'uuid' => ProxmoxVm::query()->where('host_id', $host->id)->where('vmid', (int) $data['vmid'])->value('uuid') ?? (string) Str::uuid(),
                'node' => $machine['node'],
                'user_id' => $user->id,
                'name' => trim((string) ($data['name'] ?? '')) ?: ($machine['name'] ?: 'VPS ' . $data['vmid']),
            ],
        );
        $this->alert->success('The VPS was given to ' . $user->username . '.')->flash();

        return redirect()->route('admin.proxmox');
    }

    /**
     * Creates a VPS for a user: a copy of a template, with the cores, memory, disk and address chosen here.
     */
    public function create(Request $request, ProxmoxHost $host): RedirectResponse
    {
        $data = $request->validate([
            'user' => ['required', 'string', 'max:191'],
            'name' => ['required', 'string', 'max:63'],
            'template' => ['required', 'integer', 'min:100'],
            'cores' => ['required', 'integer', 'min:1', 'max:64'],
            'memory' => ['required', 'integer', 'min:256', 'max:524288'],
            'disk' => ['required', 'integer', 'min:1', 'max:16384'],
            'ip' => ['nullable', 'integer'],
            'ssh_key' => ['nullable', 'string', 'max:4000'],
        ]);

        $user = User::query()->where('email', $data['user'])->orWhere('username', $data['user'])->first();
        if (!$user) {
            $this->alert->danger('No user has this email or username.')->flash();

            return redirect()->route('admin.proxmox')->withInput();
        }

        try {
            $this->provisioner->create($host, $user, $data);
        } catch (DisplayException $exception) {
            $this->alert->danger($exception->getMessage())->flash();

            return redirect()->route('admin.proxmox')->withInput();
        }
        $this->alert->success('The VPS is being created. It takes a few minutes; it already shows in the panel of its user.')->flash();

        return redirect()->route('admin.proxmox');
    }

    public function retry(ProxmoxVm $vm): RedirectResponse
    {
        if ($vm->state === 'failed') {
            $this->provisioner->retry($vm);
            $this->alert->success('The creation of the VPS starts again.')->flash();
        }

        return redirect()->route('admin.proxmox');
    }

    /**
     * Deletes the machine on Proxmox, with its disks, and takes it out of the panel.
     */
    public function destroy(ProxmoxVm $vm): RedirectResponse
    {
        $this->provisioner->delete($vm);
        $this->alert->success('The VPS is being deleted on Proxmox.')->flash();

        return redirect()->route('admin.proxmox');
    }

    /**
     * Adds public addresses to the host, one per line ("203.0.113.5", "203.0.113.5/32", or followed by the virtual MAC
     * address the host gave for it), with the netmask and the gateway they share.
     */
    public function addIps(Request $request, ProxmoxHost $host): RedirectResponse
    {
        $data = $request->validate([
            'addresses' => ['required', 'string', 'max:20000'],
            'cidr' => ['required', 'integer', 'min:1', 'max:32'],
            'gateway' => ['required', 'ipv4'],
        ]);

        $added = 0;
        $skipped = [];
        foreach (preg_split('/\r?\n/', $data['addresses']) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = preg_split('/[\s,;]+/', $line) ?: [];
            [$address, $cidr] = array_pad(explode('/', (string) $parts[0], 2), 2, null);
            $mac = isset($parts[1]) ? strtolower($parts[1]) : null;
            if (!filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
                || ($cidr !== null && (!ctype_digit($cidr) || (int) $cidr < 1 || (int) $cidr > 32))
                || ($mac !== null && preg_match('/^([0-9a-f]{2}:){5}[0-9a-f]{2}$/', $mac) !== 1)
                || ProxmoxIp::query()->where('address', $address)->exists()) {
                $skipped[] = $line;
                continue;
            }
            ProxmoxIp::query()->create([
                'host_id' => $host->id,
                'address' => $address,
                'cidr' => $cidr !== null ? (int) $cidr : (int) $data['cidr'],
                'gateway' => $data['gateway'],
                'mac' => $mac,
            ]);
            ++$added;
        }

        if ($added > 0) {
            $this->alert->success('The IP addresses were added.')->flash();
        }
        if ($skipped) {
            $this->alert->warning('Left out (wrong or already there): ' . implode(', ', array_slice($skipped, 0, 10)))->flash();
        }

        return redirect()->route('admin.proxmox');
    }

    public function deleteIp(ProxmoxIp $ip): RedirectResponse
    {
        if ($ip->vm_id !== null) {
            $this->alert->danger('This address is used by a VPS.')->flash();
        } else {
            $ip->delete();
            $this->alert->success('The address was removed.')->flash();
        }

        return redirect()->route('admin.proxmox');
    }

    public function unassign(ProxmoxVm $vm): RedirectResponse
    {
        $vm->delete();
        $this->alert->success('The VPS was taken back. The machine itself is untouched on Proxmox.')->flash();

        return redirect()->route('admin.proxmox');
    }

    private function save(Request $request, ?ProxmoxHost $host): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'url' => ['required', 'string', 'max:191'],
            'token_id' => ['required', 'string', 'max:191', 'regex:/^[^@\s]+@[^!\s]+![A-Za-z0-9._-]+$/'],
            'token_secret' => [$host ? 'nullable' : 'required', 'string', 'max:191'],
        ], [
            'token_id.regex' => 'The token ID looks like root@pam!panel.',
        ]);
        $data['verify_tls'] = $request->boolean('verify_tls');

        try {
            $host = $this->proxmox->saveHost($data, $host);
        } catch (DisplayException $exception) {
            $this->alert->danger($exception->getMessage())->flash();

            return redirect()->route('admin.proxmox')->withInput($request->except('token_secret'));
        }

        // Saved: check right away that it answers.
        return $this->test($host);
    }
}
