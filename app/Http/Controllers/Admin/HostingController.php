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
        $eggs = Egg::query()->with('nest:id,name')->orderBy('name')->get(['id', 'nest_id', 'name', 'docker_images']);
        if (!$plan->egg_id && ($first = $eggs->first(fn (Egg $egg) => self::looksLikeWeb($egg)))) {
            $plan->egg_id = $first->id;
        }

        return view('admin.hosting.plan', [
            'plan' => $plan,
            'eggs' => $eggs,
            'suggested' => $eggs->filter(fn (Egg $egg) => self::looksLikeWeb($egg))->values(),
            'others' => $eggs->reject(fn (Egg $egg) => self::looksLikeWeb($egg))->groupBy(fn (Egg $egg) => $egg->nest?->name ?? '—'),
            // What the form needs to show, for each egg, the images it can run: label => image.
            'images' => $eggs->mapWithKeys(fn (Egg $egg) => [$egg->id => (object) ($egg->docker_images ?? [])])->all(),
            'locations' => Location::query()->orderBy('short')->get(['id', 'short']),
            // The versions this plan already has: image => name.
            'chosen' => array_flip($plan->php_versions ?? []),
            'chosenDefault' => $plan->default_php,
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
            'img' => ['nullable', 'array', 'max:50'],
            'img.*.image' => ['nullable', 'string', 'max:255'],
            'img.*.label' => ['nullable', 'string', 'max:40'],
            'img.*.use' => ['nullable'],
            'default_index' => ['nullable', 'integer', 'min:0'],
            'position' => ['nullable', 'integer', 'min:0'],
            'environment' => ['nullable', 'string', 'max:4000'],
        ]);

        [$versions, $default] = $this->versionsOf(Egg::query()->findOrFail((int) $data['egg_id']), (array) ($data['img'] ?? []), isset($data['default_index']) ? (int) $data['default_index'] : null);

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
            'default_php' => $default,
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
     * Whether an egg looks like something that serves web pages, by its name or the name of its nest. Only used to show
     * these first: any egg can still be chosen.
     */
    private static function looksLikeWeb(Egg $egg): bool
    {
        return preg_match('/php|web|nginx|apache|caddy|wordpress|http|lamp|lemp|site/i', $egg->name . ' ' . ($egg->nest?->name ?? '')) === 1;
    }

    /**
     * The versions of PHP of a plan, taken from the images of the egg: a row of the form says whether an image is used,
     * and the name that the client sees for it. An image that the egg does not have cannot be used, so the versions are
     * always consistent with the egg that was chosen.
     *
     * @param array<int, array<string, mixed>> $rows
     *
     * @return array{0: array<string, string>, 1: string|null} the versions (name => image) and the one to start with
     *
     * @throws ValidationException
     */
    private function versionsOf(Egg $egg, array $rows, ?int $defaultIndex): array
    {
        $available = array_values($egg->docker_images ?? []);
        $labels = array_flip($egg->docker_images ?? []);
        $versions = [];
        $default = null;

        foreach ($rows as $index => $row) {
            if (empty($row['use'])) {
                continue;
            }
            $image = (string) ($row['image'] ?? '');
            if (!in_array($image, $available, true)) {
                throw ValidationException::withMessages(['img' => 'An image is not one of the images of this egg.']);
            }
            $name = trim((string) ($row['label'] ?? ''));
            $name = $name !== '' ? $name : (string) ($labels[$image] ?? $image);
            if (preg_match('/^[\p{L}\p{N} .+_-]{1,20}$/u', $name) !== 1) {
                throw ValidationException::withMessages(['img' => '"' . $name . '" cannot be used as the name of a version (20 characters at most: letters, digits, spaces and . + _ -).']);
            }
            if (isset($versions[$name])) {
                throw ValidationException::withMessages(['img' => 'Two versions have the same name: "' . $name . '".']);
            }
            $versions[$name] = $image;
            if ($defaultIndex !== null && (int) $index === $defaultIndex) {
                $default = $name;
            }
        }

        return [$versions, $default];
    }
}
