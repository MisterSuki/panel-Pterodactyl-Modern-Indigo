<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Models\Permission;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerSubdomain;
use Pterodactyl\Models\ServerWebDomain;
use Pterodactyl\Models\SubdomainDomain;
use Pterodactyl\Services\Subdomains\SubdomainService;
use Pterodactyl\Services\WebHosting\WebHostingService;
use Pterodactyl\Services\WebHosting\WebInstaller;

/**
 * The Website tab of a site: its domains and the installation of a CMS. Seeing it needs the right to see the network of
 * the server; changing the domains the rights to change it; installing a CMS the right to create databases and files.
 */
class WebHostingController extends ClientApiController
{
    public function __construct(
        private WebHostingService $web,
        private WebInstaller $installer,
        private SubdomainService $subdomains,
    ) {
        parent::__construct();
    }

    public function index(Request $request, Server $server): JsonResponse
    {
        $this->authorizeFor($request, $server, Permission::ACTION_ALLOCATION_READ);

        return new JsonResponse($this->payload($server));
    }

    public function addDomain(Request $request, Server $server): JsonResponse
    {
        $this->authorizeFor($request, $server, Permission::ACTION_ALLOCATION_CREATE);
        $data = $request->validate(['hostname' => ['required', 'string', 'max:191']]);

        $domain = $this->web->addOwnDomain($server, $data['hostname']);
        Activity::event('server:subdomain.custom-add')->property(['domain' => $domain->hostname])->log();

        return new JsonResponse($this->payload($server));
    }

    public function addSubdomain(Request $request, Server $server): JsonResponse
    {
        $this->authorizeFor($request, $server, Permission::ACTION_ALLOCATION_CREATE);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:63'],
            'domain_id' => ['required', 'integer'],
        ]);

        $zone = SubdomainDomain::query()->where('available', true)->findOrFail($data['domain_id']);
        $domain = $this->web->addSubdomain($server, $zone, $data['name']);
        Activity::event('server:subdomain.create')->property(['subdomain' => $domain->hostname])->log();

        return new JsonResponse($this->payload($server));
    }

    public function checkDomain(Request $request, Server $server, int $domain): JsonResponse
    {
        $this->authorizeFor($request, $server, Permission::ACTION_ALLOCATION_READ);
        $model = $this->find($server, $domain);
        if (!$this->web->check($model)) {
            throw new DisplayException($this->web->dnsProblem($model, $request->user()->language));
        }

        return new JsonResponse($this->payload($server));
    }

    public function deleteDomain(Request $request, Server $server, int $domain): JsonResponse
    {
        $this->authorizeFor($request, $server, Permission::ACTION_ALLOCATION_DELETE);
        $model = $this->find($server, $domain);
        $this->web->remove($model);
        Activity::event('server:subdomain.custom-remove')->property(['domain' => $model->hostname])->log();

        return new JsonResponse($this->payload($server));
    }

    public function install(Request $request, Server $server): JsonResponse
    {
        $this->authorizeFor($request, $server, Permission::ACTION_DATABASE_CREATE);
        $this->authorizeFor($request, $server, Permission::ACTION_FILE_CREATE);
        $this->authorizeFor($request, $server, Permission::ACTION_FILE_DELETE);

        $app = (string) $request->input('app');
        $account = (WebInstaller::APPS[$app]['account'] ?? false) ? 'required' : 'nullable';
        $data = $request->validate([
            'app' => ['required', 'string', Rule::in(array_keys(WebInstaller::APPS))],
            'title' => [$account, 'string', 'max:100'],
            'admin_user' => [$account, 'string', 'min:3', 'max:40', 'regex:/^[A-Za-z0-9._-]+$/'],
            'admin_password' => [$account, 'string', 'min:12', 'max:100'],
            'admin_email' => [$account, 'email', 'max:191'],
            'locale' => ['required', 'string', Rule::in(['en_US', 'fr_FR', 'de_DE', 'es_ES', 'it_IT', 'pt_PT', 'nl_NL'])],
        ]);
        if (!WebHostingService::isWebsite($server) || !$this->isPhp($server)) {
            throw new DisplayException('A CMS can only be installed on a PHP site.');
        }

        $this->installer->start($server, $data);
        Activity::event('server:web.install')->property(['app' => WebInstaller::APPS[$data['app']]['name']])->log();

        return new JsonResponse($this->payload($server));
    }

    public function uninstall(Request $request, Server $server): JsonResponse
    {
        $this->authorizeFor($request, $server, Permission::ACTION_DATABASE_DELETE);
        $this->authorizeFor($request, $server, Permission::ACTION_FILE_DELETE);
        if (!WebHostingService::isWebsite($server) || !$this->isPhp($server)) {
            throw new DisplayException('A CMS can only be installed on a PHP site.');
        }

        $this->installer->uninstall($server);
        Activity::event('server:web.uninstall')->log();

        return new JsonResponse($this->payload($server));
    }

    public function status(Request $request, Server $server): JsonResponse
    {
        $this->authorizeFor($request, $server, Permission::ACTION_ALLOCATION_READ);

        return new JsonResponse(['install' => $this->installer->status($server)]);
    }

    private function authorizeFor(Request $request, Server $server, string $permission): void
    {
        abort_unless($request->user()->can($permission, $server), 403);
    }

    private function find(Server $server, int $id): ServerWebDomain
    {
        return ServerWebDomain::query()->with('custom.allocation.node')->where('server_id', $server->id)->findOrFail($id);
    }

    private function isPhp(Server $server): bool
    {
        return str_starts_with((string) $server->image, 'modern-indigo/web:');
    }

    private function payload(Server $server): array
    {
        $server->loadMissing('allocation.node');
        $agent = $this->web->agentOf($server->node);
        $used = ServerSubdomain::query()->where('server_id', $server->id)->count();
        $max = $this->web->maxDomains();
        $full = ServerWebDomain::query()->where('server_id', $server->id)->count() >= $max;

        return [
            'php' => $this->isPhp($server),
            'agent' => $agent !== null && $agent['online'],
            'url' => $this->web->primaryUrl($server),
            'preview' => $this->web->previewUrl($server),
            'max' => $max,
            // The domains of the panel a subdomain can be made on, while the site and the server have room for one.
            'zones' => !$full && $used < $this->subdomains->limitFor($server)
                ? SubdomainDomain::query()->where('available', true)->orderBy('domain')->get()
                    ->map(fn (SubdomainDomain $d) => ['id' => $d->id, 'domain' => $d->domain])->values()
                : [],
            'domains' => $this->web->domainsOf($server)->map(fn (ServerWebDomain $d) => [
                'id' => $d->id,
                'hostname' => $d->hostname,
                'own' => $d->custom_domain_id !== null,
                'www' => $d->www,
                'ready' => $d->ready(),
                'ssl' => $d->ssl_status,
                'ssl_error' => $d->ssl_error,
                'url' => $d->ready() ? $d->url() : null,
                'records' => $this->web->records($d),
            ])->values(),
            'apps' => collect(WebInstaller::APPS)
                ->map(fn (array $app, string $id) => ['id' => $id] + $app)->values(),
            'install' => $this->isPhp($server) ? $this->installer->status($server) : null,
        ];
    }
}
