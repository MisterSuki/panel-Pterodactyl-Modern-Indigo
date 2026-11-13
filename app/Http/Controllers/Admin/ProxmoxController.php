<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\ProxmoxHost;
use Pterodactyl\Models\ProxmoxVm;
use Pterodactyl\Models\User;
use Pterodactyl\Services\Proxmox\ProxmoxService;

/**
 * Proxmox seen from the administration: the hosts the panel is connected to (address and API token), the state of
 * their nodes and storage, and their virtual machines, which can be given to users as VPS. It needs the right to manage
 * the settings of the panel.
 */
class ProxmoxController extends Controller
{
    public function __construct(private ProxmoxService $proxmox, private AlertsMessageBag $alert)
    {
    }

    public function index(Request $request): View
    {
        $hosts = ProxmoxHost::query()->orderBy('name')->get();
        $fresh = $request->boolean('refresh');
        $assigned = ProxmoxVm::query()->with('user:id,username,email')->get()->groupBy('host_id');

        return view('admin.proxmox', [
            'hosts' => $hosts->map(fn (ProxmoxHost $host) => [
                'host' => $host,
                'overview' => $this->proxmox->overview($host, $fresh),
                'assigned' => ($assigned->get($host->id) ?? collect())->keyBy('vmid'),
            ]),
            'editing' => $request->filled('edit') ? $hosts->firstWhere('id', (int) $request->query('edit')) : null,
        ]);
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
