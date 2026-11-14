<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\ServerWebDomain;
use Pterodactyl\Models\User;
use Pterodactyl\Services\WebHosting\WebHostingService;
use Pterodactyl\Services\WebHosting\WebHostingSetup;

/**
 * The websites seen from the administration: whether each node is ready, the eggs, and every domain with its certificate.
 */
class WebHostingController extends Controller
{
    public function __construct(
        private WebHostingService $web,
        private WebHostingSetup $setup,
        private AlertsMessageBag $alert,
    ) {
    }

    public function index(): View
    {
        return view('admin.web-hosting', [
            'nodes' => Node::query()->orderBy('name')->get()->map(fn (Node $node) => ['node' => $node, 'agent' => $this->web->agentOf($node)]),
            'domains' => ServerWebDomain::query()->with(['server', 'custom'])->orderBy('hostname')->limit(500)->get(),
            'eggsInstalled' => $this->setup->installed(),
            'email' => $this->web->acmeEmail(),
            'maxDomains' => $this->web->maxDomains(),
        ]);
    }

    public function eggs(): RedirectResponse
    {
        $names = $this->setup->run();
        $this->alert->success('The eggs are ready: ' . implode(', ', $names) . '.')->flash();

        return redirect()->route('admin.web-hosting');
    }

    /**
     * A website in a few fields: name, owner, resources. The node and the port are found alone; the kind of site
     * (PHP / HTML, Node.js, Python) is chosen by the owner in their panel.
     */
    public function create(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'user' => ['required', 'string', 'max:191'],
            'memory' => ['required', 'numeric', 'min:0.125', 'max:1048576'],
            'memory_unit' => ['required', 'in:MB,GB'],
            'disk' => ['required', 'numeric', 'min:0.125', 'max:1048576'],
            'disk_unit' => ['required', 'in:MB,GB'],
            'cpu' => ['required', 'integer', 'min:0', 'max:10000'],
            'node_id' => ['nullable', 'integer', 'exists:nodes,id'],
        ]);
        $owner = User::query()->where('email', $data['user'])->orWhere('username', $data['user'])->first();
        if (!$owner) {
            $this->alert->danger('No user has this email or username.')->flash();

            return redirect()->route('admin.web-hosting')->withInput();
        }
        $mb = fn ($value, $unit) => max(64, (int) round((float) $value * ($unit === 'GB' ? 1024 : 1)));

        $server = app(\Pterodactyl\Services\WebHosting\WebsiteService::class)->create(
            $owner,
            $data['name'],
            $mb($data['memory'], $data['memory_unit']),
            $mb($data['disk'], $data['disk_unit']),
            (int) $data['cpu'],
            isset($data['node_id']) ? (int) $data['node_id'] : null,
        );
        $this->alert->success('The website was created for ' . $owner->username . '. They choose its kind (PHP, Node.js, Python) in the Website tab.')->flash();

        return redirect()->route('admin.servers.view', $server->id);
    }

    public function settings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['nullable', 'email', 'max:191'],
            'max_domains' => ['required', 'integer', 'min:1', 'max:50'],
        ]);
        $this->web->setAcmeEmail((string) ($data['email'] ?? ''));
        $this->web->setMaxDomains((int) $data['max_domains']);
        $this->alert->success('The settings were saved.')->flash();

        return redirect()->route('admin.web-hosting');
    }
}
