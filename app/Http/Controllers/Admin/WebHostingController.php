<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\ServerWebDomain;
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
        ]);
    }

    public function eggs(): RedirectResponse
    {
        $names = $this->setup->run();
        $this->alert->success('The eggs are ready: ' . implode(', ', $names) . '.')->flash();

        return redirect()->route('admin.web-hosting');
    }

    public function settings(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['nullable', 'email', 'max:191']]);
        $this->web->setAcmeEmail((string) ($data['email'] ?? ''));
        $this->alert->success('The settings were saved.')->flash();

        return redirect()->route('admin.web-hosting');
    }
}
