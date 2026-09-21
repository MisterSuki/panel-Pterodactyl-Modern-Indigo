<?php

namespace Pterodactyl\Http\Controllers\Api\Client;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Models\User;
use Pterodactyl\Models\WebAccount;
use Pterodactyl\Models\WebDomain;
use Pterodactyl\Models\WebSite;
use Pterodactyl\Services\Web\WebHostingService;
use Pterodactyl\Services\Web\WebHostingSettings;

/**
 * The web hosting, seen by the client: their sites, and what they can do with them (add and remove domains, check that a
 * domain leads to the web server, change the version of PHP, make another site if the plan allows). Everything is limited
 * to what belongs to the person who asks.
 */
class HostingController extends ClientApiController
{
    public function __construct(private WebHostingService $hosting, private WebHostingSettings $settings)
    {
        parent::__construct();
    }

    public function index(Request $request): JsonResponse
    {
        if (!$this->settings->enabled()) {
            return new JsonResponse(['enabled' => false]);
        }

        $accounts = WebAccount::query()->with(['plan', 'sites.domains', 'sites.server:id,uuidShort,name'])->where('user_id', $request->user()->id)->orderBy('id')->get();

        return new JsonResponse([
            'enabled' => true,
            'ips' => $this->settings->serverIps(),
            'base_domain' => $this->settings->baseDomain(),
            'accounts' => $accounts->map(fn (WebAccount $account) => [
                'id' => $account->id,
                'plan' => $account->plan_name,
                'status' => $account->status,
                'max_sites' => $account->max_sites,
                'max_domains' => $account->max_domains,
                'can_create_site' => $account->status === WebAccount::ACTIVE && $account->plan && $account->plan->enabled && $account->sites->count() < $account->max_sites,
                'sites' => $account->sites->map(fn (WebSite $site) => [
                    'id' => $site->id,
                    'name' => $site->name,
                    'status' => $site->status,
                    'php_version' => $site->php_version,
                    'php_versions' => $account->plan ? $account->plan->versions() : [],
                    'server' => $site->server ? ['identifier' => $site->server->uuidShort, 'name' => $site->server->name] : null,
                    'domains' => $site->domains->map(fn (WebDomain $domain) => [
                        'id' => $domain->id,
                        'domain' => $domain->domain,
                        'include_www' => $domain->include_www,
                        'is_primary' => $domain->is_primary,
                        'status' => $domain->status,
                    ])->all(),
                ])->all(),
            ])->all(),
        ]);
    }

    public function createSite(Request $request): JsonResponse
    {
        abort_unless($this->settings->enabled(), 404);
        $data = $request->validate([
            'account_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:80'],
            'domain' => ['nullable', 'string', 'max:253'],
        ]);
        $account = WebAccount::query()->where('user_id', $request->user()->id)->findOrFail((int) $data['account_id']);

        $site = $this->hosting->createSite($account, $data['name'], $data['domain'] ?? null);

        return new JsonResponse(['id' => $site->id], 201);
    }

    public function addDomain(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['domain' => ['required', 'string', 'max:253'], 'include_www' => ['nullable', 'boolean']]);
        $domain = $this->hosting->addDomain($this->site($request->user(), $id), $data['domain'], (bool) ($data['include_www'] ?? false));

        return new JsonResponse(['id' => $domain->id, 'status' => $domain->status], 201);
    }

    public function removeDomain(Request $request, int $id, int $domainId): JsonResponse
    {
        $site = $this->site($request->user(), $id);
        $this->hosting->removeDomain($site, $this->domain($site, $domainId));

        return new JsonResponse([], 204);
    }

    public function verifyDomain(Request $request, int $id, int $domainId): JsonResponse
    {
        $site = $this->site($request->user(), $id);
        $verified = $this->hosting->verify($this->domain($site, $domainId));

        return new JsonResponse(['verified' => $verified]);
    }

    public function setPhp(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['version' => ['required', 'string', 'max:10']]);
        $site = $this->hosting->setPhpVersion($this->site($request->user(), $id), $data['version']);

        return new JsonResponse(['php_version' => $site->php_version]);
    }

    /**
     * A site of the person who asks. A site of somebody else is a "not found", as if it did not exist.
     */
    private function site(User $user, int $id): WebSite
    {
        abort_unless($this->settings->enabled(), 404);
        $site = WebSite::query()->with('account')->findOrFail($id);
        if ($site->account->user_id !== $user->id) {
            abort(404);
        }
        if ($site->account->status !== WebAccount::ACTIVE) {
            throw new DisplayException('This hosting account is suspended.');
        }

        return $site;
    }

    private function domain(WebSite $site, int $id): WebDomain
    {
        return WebDomain::query()->where('site_id', $site->id)->findOrFail($id);
    }
}
