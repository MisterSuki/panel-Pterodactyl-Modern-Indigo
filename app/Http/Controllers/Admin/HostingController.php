<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Http\Controllers\Admin\Concerns\ReadsEggEnvironment;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Location;
use Pterodactyl\Models\User;
use Pterodactyl\Models\WebAccount;
use Pterodactyl\Models\WebPlan;
use Pterodactyl\Models\WebSite;
use Pterodactyl\Services\Web\WebHostingService;
use Pterodactyl\Services\Web\WebHostingSettings;

/**
 * The web hosting, seen by the administration: the clients and their accounts, the plans, the sites, and the settings of
 * the web server. Seeing is for the staff who can see the hosting, anything that changes something needs the right to
 * manage it (the middleware asks for it on every request that is not a read), and the settings are for full
 * administrators.
 */
class HostingController extends Controller
{
    use ReadsEggEnvironment;

    public function __construct(private WebHostingService $hosting, private WebHostingSettings $settings, private AlertsMessageBag $alert)
    {
    }

    // ---- clients --------------------------------------------------------------------------------------------------

    public function clients(): View
    {
        return view('admin.hosting.clients', [
            'accounts' => WebAccount::query()->with('user:id,username,email')->withCount('sites')->orderByDesc('id')->paginate(50),
        ]);
    }

    public function clientForm(): View
    {
        return view('admin.hosting.client-new', [
            'plans' => WebPlan::query()->where('enabled', true)->orderBy('position')->orderBy('name')->get(),
            'baseDomain' => $this->settings->baseDomain(),
        ]);
    }

    /**
     * Makes a client with a plan and a first site. If the person already has an account on the panel they can be given
     * the plan without making a new one.
     */
    public function createClient(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:web_plans,id'],
            'site_name' => ['required', 'string', 'max:80'],
            'domain' => ['nullable', 'string', 'max:253'],
            'existing' => ['nullable', 'string', 'max:191'],
            'username' => ['nullable', 'string', 'between:1,191'],
            'email' => ['nullable', 'email', 'max:191'],
            'name_first' => ['nullable', 'string', 'max:191'],
            'name_last' => ['nullable', 'string', 'max:191'],
            'password' => ['nullable', 'string', 'min:8', 'max:191'],
        ]);
        $plan = WebPlan::query()->findOrFail((int) $data['plan_id']);

        try {
            if (!empty($data['existing'])) {
                $user = User::query()->where('username', $data['existing'])->orWhere('email', $data['existing'])->first();
                if (!$user) {
                    throw ValidationException::withMessages(['existing' => 'No user has this name or email.']);
                }
                $account = $this->hosting->giveAccount($user, $plan);
                $this->hosting->createSite($account, $data['site_name'], $data['domain'] ?? null);
            } else {
                foreach (['username', 'email', 'name_first', 'name_last'] as $field) {
                    if (empty($data[$field])) {
                        throw ValidationException::withMessages([$field => 'This field is needed to make a new client (or give the name of an existing user).']);
                    }
                }
                [$user, $account] = $this->hosting->createClient([
                    'username' => $data['username'],
                    'email' => $data['email'],
                    'name_first' => $data['name_first'],
                    'name_last' => $data['name_last'],
                    'password' => $data['password'] ?? null,
                    'language' => app()->getLocale(),
                ], $plan, $data['site_name'], $data['domain'] ?? null);
            }
        } catch (DisplayException $exception) {
            throw ValidationException::withMessages(['site_name' => $exception->getMessage()]);
        }

        $this->alert->success('The client ' . $user->username . ' has their hosting.')->flash();

        return redirect()->route('admin.hosting.account', $account->id);
    }

    public function account(int $id): View
    {
        return view('admin.hosting.account', [
            'account' => WebAccount::query()->with(['user:id,username,email', 'plan', 'sites.domains', 'sites.server:id,uuidShort'])->findOrFail($id),
        ]);
    }

    public function addSite(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['site_name' => ['required', 'string', 'max:80'], 'domain' => ['nullable', 'string', 'max:253']]);

        try {
            $this->hosting->createSite(WebAccount::query()->findOrFail($id), $data['site_name'], $data['domain'] ?? null);
        } catch (DisplayException $exception) {
            throw ValidationException::withMessages(['site_name' => $exception->getMessage()]);
        }
        $this->alert->success('The site was made.')->flash();

        return redirect()->route('admin.hosting.account', $id);
    }

    public function toggleAccount(int $id): RedirectResponse
    {
        $account = WebAccount::query()->findOrFail($id);
        $suspend = $account->status === WebAccount::ACTIVE;
        $this->hosting->setAccountSuspended($account, $suspend);
        $this->alert->success($suspend ? 'The account is suspended: its sites are stopped.' : 'The account is active again.')->flash();

        return redirect()->route('admin.hosting.account', $id);
    }

    public function deleteSite(int $siteId): RedirectResponse
    {
        $site = WebSite::query()->findOrFail($siteId);
        $account = $site->account_id;
        $this->hosting->deleteSite($site);
        $this->alert->success('The site was deleted, with its server and its files.')->flash();

        return redirect()->route('admin.hosting.account', $account);
    }

    public function sites(): View
    {
        return view('admin.hosting.sites', [
            'sites' => WebSite::query()->with(['account.user:id,username', 'domains', 'server:id,uuidShort'])->orderByDesc('id')->paginate(50),
        ]);
    }

    // ---- plans ----------------------------------------------------------------------------------------------------

    public function plans(): View
    {
        return view('admin.hosting.plans', [
            'plans' => WebPlan::query()->orderBy('position')->orderBy('name')->get(),
        ]);
    }

    public function planForm(?int $id = null): View
    {
        $plan = $id ? WebPlan::query()->findOrFail($id) : new WebPlan(['max_sites' => 1, 'max_domains' => 3, 'memory' => 512, 'disk' => 5120, 'cpu' => 0, 'database_limit' => 1, 'backup_limit' => 1, 'enabled' => true]);

        return view('admin.hosting.plan', [
            'plan' => $plan,
            'eggs' => Egg::query()->with('nest:id,name')->orderBy('name')->get(['id', 'nest_id', 'name']),
            'locations' => Location::query()->orderBy('short')->get(['id', 'short']),
            'versions' => collect($plan->php_versions ?? [])->map(fn ($image, $version) => $version . '=' . $image)->implode("\n"),
            'environment' => collect($plan->environment ?? [])->map(fn ($value, $name) => $name . '=' . $value)->implode("\n"),
        ]);
    }

    public function savePlan(Request $request, ?int $id = null): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:1000'],
            'egg_id' => ['required', 'integer', 'exists:eggs,id'],
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'max_sites' => ['required', 'integer', 'between:1,100'],
            'max_domains' => ['required', 'integer', 'between:1,100'],
            'memory' => ['required', 'integer', 'min:64'],
            'disk' => ['required', 'integer', 'min:64'],
            'cpu' => ['required', 'integer', 'min:0'],
            'database_limit' => ['nullable', 'integer', 'min:0'],
            'backup_limit' => ['nullable', 'integer', 'min:0'],
            'versions' => ['nullable', 'string', 'max:4000'],
            'default_php' => ['nullable', 'string', 'max:10'],
            'position' => ['nullable', 'integer', 'min:0'],
            'environment' => ['nullable', 'string', 'max:4000'],
        ]);

        $versions = $this->versions((string) ($data['versions'] ?? ''));
        $default = (string) ($data['default_php'] ?? '');
        if ($default !== '' && !array_key_exists($default, $versions)) {
            throw ValidationException::withMessages(['default_php' => 'The version to start with has to be one of the versions of the list.']);
        }

        $plan = $id ? WebPlan::query()->findOrFail($id) : new WebPlan();
        $plan->fill([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'egg_id' => (int) $data['egg_id'],
            'location_id' => (int) $data['location_id'],
            'max_sites' => (int) $data['max_sites'],
            'max_domains' => (int) $data['max_domains'],
            'memory' => (int) $data['memory'],
            'disk' => (int) $data['disk'],
            'cpu' => (int) $data['cpu'],
            'database_limit' => (int) ($data['database_limit'] ?? 0),
            'backup_limit' => (int) ($data['backup_limit'] ?? 0),
            'php_versions' => $versions ?: null,
            'default_php' => $default !== '' ? $default : null,
            'position' => (int) ($data['position'] ?? 0),
            'environment' => $this->eggEnvironment((string) ($data['environment'] ?? ''), (int) $data['egg_id']) ?: null,
            'enabled' => $request->boolean('enabled'),
        ])->save();
        $this->alert->success('The plan was saved. Accounts that already have it keep their limits.')->flash();

        return redirect()->route('admin.hosting.plans');
    }

    public function deletePlan(int $id): RedirectResponse
    {
        // Accounts keep their limits; only new sites can no longer be made from a plan that is gone.
        WebPlan::query()->findOrFail($id)->delete();
        $this->alert->success('The plan was deleted. Accounts keep what they have, but cannot make new sites.')->flash();

        return redirect()->route('admin.hosting.plans');
    }

    // ---- settings -------------------------------------------------------------------------------------------------

    public function settings(): View
    {
        return view('admin.hosting.settings', [
            'enabled' => $this->settings->enabled(),
            'serverIps' => (string) $this->settings->get('server_ips', ''),
            'baseDomain' => (string) $this->settings->get('base_domain', ''),
            'hasToken' => $this->settings->hasToken(),
            'configUrl' => url('/api/hosting/proxy-config'),
            'token' => session('web-token'),
        ]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'server_ips' => ['nullable', 'string', 'max:500'],
            'base_domain' => ['nullable', 'string', 'max:253'],
        ]);

        $ips = trim((string) ($data['server_ips'] ?? ''));
        foreach (preg_split('/[\s,;]+/', $ips) ?: [] as $ip) {
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) === false) {
                throw ValidationException::withMessages(['server_ips' => '"' . $ip . '" is not an IP address.']);
            }
        }
        $base = strtolower(trim((string) ($data['base_domain'] ?? ''), " ."));
        if ($base !== '') {
            $this->settings->set('base_domain', $base);
            if ($this->settings->baseDomain() === null) {
                $this->settings->set('base_domain', null);
                throw ValidationException::withMessages(['base_domain' => 'This is not a valid domain name.']);
            }
        } else {
            $this->settings->set('base_domain', null);
        }

        $this->settings->set('enabled', $request->boolean('enabled') ? '1' : '0');
        $this->settings->set('server_ips', $ips);
        $this->alert->success('The settings of the web hosting were saved.')->flash();

        return redirect()->route('admin.hosting.settings');
    }

    /**
     * Makes a new token for the web server. It is shown once, right after, and never again; the one that was there stops
     * working.
     */
    public function newToken(): RedirectResponse
    {
        return redirect()->route('admin.hosting.settings')->with('web-token', $this->settings->newToken());
    }

    // ---- helpers --------------------------------------------------------------------------------------------------

    /**
     * The versions of PHP of a plan, written "8.3=image" one per line.
     *
     * @return array<string, string>
     *
     * @throws ValidationException
     */
    private function versions(string $text): array
    {
        $versions = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            [$version, $image] = array_pad(explode('=', $line, 2), 2, '');
            $version = trim($version);
            $image = trim($image);
            if (preg_match('/^\d{1,2}\.\d{1,2}$/', $version) !== 1 || preg_match('#^[A-Za-z0-9][A-Za-z0-9._/:@-]{1,250}$#', $image) !== 1) {
                throw ValidationException::withMessages(['versions' => '"' . $line . '" is not written as 8.3=ghcr.io/example/image:tag.']);
            }
            $versions[$version] = $image;
        }

        return $versions;
    }
}
