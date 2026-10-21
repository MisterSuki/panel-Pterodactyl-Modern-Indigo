<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Models\Permission;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerSubdomain;
use Pterodactyl\Models\SubdomainDomain;
use Pterodactyl\Models\ServerCustomDomain;
use Pterodactyl\Services\Subdomains\CustomDomainService;
use Pterodactyl\Services\Subdomains\SubdomainService;

/**
 * The subdomains of a server. Seeing them needs the right to see the network of the server; making, changing and
 * removing them the rights to change it.
 */
class SubdomainController extends ClientApiController
{
    public function __construct(private SubdomainService $service, private CustomDomainService $custom)
    {
        parent::__construct();
    }

    public function index(Request $request, Server $server): JsonResponse
    {
        $this->authorizeFor($request, $server, Permission::ACTION_ALLOCATION_READ);

        return new JsonResponse($this->payload($server, $request));
    }

    public function store(Request $request, Server $server): JsonResponse
    {
        $this->authorizeFor($request, $server, Permission::ACTION_ALLOCATION_CREATE);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:63'],
            'domain_id' => ['required', 'integer'],
            'allocation_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:191'],
        ]);

        $domain = SubdomainDomain::query()->where('available', true)->findOrFail($data['domain_id']);
        $subdomain = $this->service->create($server, $domain, $data['name'], $this->allocation($server, $data['allocation_id'] ?? null), $data['notes'] ?? null);

        Activity::event('server:subdomain.create')->property(['subdomain' => $subdomain->fqdn()])->log();

        return new JsonResponse($this->payload($server, $request));
    }

    public function update(Request $request, Server $server, int $subdomain): JsonResponse
    {
        $this->authorizeFor($request, $server, Permission::ACTION_ALLOCATION_UPDATE);
        $data = $request->validate([
            'allocation_id' => ['sometimes', 'nullable', 'integer'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:191'],
        ]);
        $model = $this->find($server, $subdomain);

        if (array_key_exists('allocation_id', $data) && (int) ($data['allocation_id'] ?? 0) !== (int) $model->allocation_id) {
            $this->service->reassign($model, $this->allocation($server, $data['allocation_id']));
            Activity::event('server:subdomain.update')->property(['subdomain' => $model->fqdn()])->log();
        }
        if (array_key_exists('notes', $data)) {
            $model->forceFill(['notes' => $data['notes']])->save();
        }

        return new JsonResponse($this->payload($server, $request));
    }

    public function delete(Request $request, Server $server, int $subdomain): JsonResponse
    {
        $this->authorizeFor($request, $server, Permission::ACTION_ALLOCATION_DELETE);
        $model = $this->find($server, $subdomain);
        $this->service->delete($model);
        Activity::event('server:subdomain.delete')->property(['subdomain' => $model->fqdn()])->log();

        return new JsonResponse($this->payload($server, $request));
    }

    /**
     * Adds an own domain: a name the user points at the server in their own DNS.
     */
    public function storeCustom(Request $request, Server $server): JsonResponse
    {
        $this->authorizeFor($request, $server, Permission::ACTION_ALLOCATION_CREATE);
        $data = $request->validate([
            'hostname' => ['required', 'string', 'max:191'],
            'allocation_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:191'],
        ]);
        $domain = $this->custom->create($server, $data['hostname'], $this->allocation($server, $data['allocation_id'] ?? null), $data['notes'] ?? null);
        Activity::event('server:subdomain.custom-add')->property(['domain' => $domain->hostname])->log();

        return new JsonResponse($this->payload($server, $request));
    }

    public function updateCustom(Request $request, Server $server, int $custom): JsonResponse
    {
        $this->authorizeFor($request, $server, Permission::ACTION_ALLOCATION_UPDATE);
        $data = $request->validate([
            'allocation_id' => ['sometimes', 'nullable', 'integer'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:191'],
        ]);
        $model = ServerCustomDomain::query()->where('server_id', $server->id)->findOrFail($custom);
        if (array_key_exists('allocation_id', $data)) {
            $model->allocation_id = $this->allocation($server, $data['allocation_id'])?->id;
            $model->save();
            $model->load('allocation.node');
            $this->custom->verify($model);
        }
        if (array_key_exists('notes', $data)) {
            $model->forceFill(['notes' => $data['notes']])->save();
        }

        return new JsonResponse($this->payload($server, $request));
    }

    public function verifyCustom(Request $request, Server $server, int $custom): JsonResponse
    {
        $this->authorizeFor($request, $server, Permission::ACTION_ALLOCATION_READ);
        $model = ServerCustomDomain::query()->with('allocation.node')->where('server_id', $server->id)->findOrFail($custom);
        $this->custom->verify($model);

        return new JsonResponse($this->payload($server, $request));
    }

    public function deleteCustom(Request $request, Server $server, int $custom): JsonResponse
    {
        $this->authorizeFor($request, $server, Permission::ACTION_ALLOCATION_DELETE);
        $model = ServerCustomDomain::query()->where('server_id', $server->id)->findOrFail($custom);
        $model->delete();
        Activity::event('server:subdomain.custom-remove')->property(['domain' => $model->hostname])->log();

        return new JsonResponse($this->payload($server, $request));
    }

    private function authorizeFor(Request $request, Server $server, string $permission): void
    {
        abort_unless($request->user()->can($permission, $server), 403);
    }

    private function find(Server $server, int $id): ServerSubdomain
    {
        return ServerSubdomain::query()->with('domain')->where('server_id', $server->id)->findOrFail($id);
    }

    /**
     * @throws DisplayException
     */
    private function allocation(Server $server, mixed $id): ?Allocation
    {
        if ($id === null || $id === '' || (int) $id === 0) {
            return null;
        }
        $allocation = $server->allocations()->with('node')->find((int) $id);
        if (!$allocation) {
            throw new DisplayException('This allocation does not belong to the server.');
        }

        return $allocation;
    }

    private function payload(Server $server, Request $request): array
    {
        $subdomains = ServerSubdomain::query()->with('domain')->where('server_id', $server->id)->orderBy('id')->get();

        return [
            // For the staff who can add domains: where to do it, shown when none is available yet.
            'manage_url' => $request->user()->canAccessAdminSection('settings') ? route('admin.subdomains') : null,
            'limit' => $this->service->limitFor($server),
            'used' => $subdomains->count(),
            'domains' => SubdomainDomain::query()->where('available', true)->orderBy('domain')->get()
                ->map(fn (SubdomainDomain $d) => ['id' => $d->id, 'domain' => $d->domain])->values(),
            'allocations' => $server->allocations()->orderBy('port')->get()
                ->map(fn (Allocation $a) => [
                    'id' => $a->id,
                    'address' => ($a->ip_alias ?: $a->ip) . ':' . $a->port,
                    'primary' => $a->id === $server->allocation_id,
                ])->values(),
            // The own domains of the server: what to create in the DNS, and whether it already leads to the server.
            'custom' => [
                'enabled' => $this->custom->enabled(),
                'max' => CustomDomainService::MAX_PER_SERVER,
                'data' => ServerCustomDomain::query()->with('allocation.node')->where('server_id', $server->id)->orderBy('id')->get()
                    ->map(fn (ServerCustomDomain $d) => [
                        'id' => $d->id,
                        'hostname' => $d->hostname,
                        'allocation_id' => $d->allocation_id,
                        'notes' => $d->notes,
                        'verified' => $d->verified_at !== null,
                        'records' => $this->custom->records($d),
                    ])->values(),
            ],
            'data' => $subdomains->map(fn (ServerSubdomain $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'domain' => $s->domain->domain ?? '',
                'fqdn' => $s->fqdn(),
                'allocation_id' => $s->allocation_id,
                'notes' => $s->notes,
                'records' => count($s->records ?? []),
            ])->values(),
        ];
    }
}
