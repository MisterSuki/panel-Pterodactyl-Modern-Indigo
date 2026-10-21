<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerSubdomain;
use Pterodactyl\Models\SubdomainDomain;
use Pterodactyl\Services\Subdomains\DnsClient;
use Pterodactyl\Models\ServerCustomDomain;
use Pterodactyl\Services\Subdomains\CustomDomainService;
use Pterodactyl\Services\Subdomains\SubdomainService;

/**
 * Where the administration offers domains for the subdomains of the servers: the DNS provider of each one and its keys,
 * the names that are not allowed, the records made for each subdomain, and the default number per server.
 */
class SubdomainController extends Controller
{
    public function __construct(
        private SubdomainService $service,
        private CustomDomainService $custom,
        private AlertsMessageBag $alert,
    ) {
    }

    public function index(): View
    {
        return view('admin.subdomains.index', [
            'domains' => SubdomainDomain::query()->withCount('subdomains')->orderBy('domain')->get(),
            'providers' => SubdomainDomain::PROVIDERS,
            'blacklist' => $this->service->blacklist(),
            'templates' => $this->service->templates(),
            'defaultLimit' => $this->service->defaultLimit(),
            'subdomains' => ServerSubdomain::query()->with(['domain', 'server', 'allocation'])->latest()->limit(200)->get(),
            'userDomains' => $this->custom->enabled(),
            'customDomains' => ServerCustomDomain::query()->with(['server', 'allocation'])->latest()->limit(200)->get(),
        ]);
    }

    public function storeDomain(Request $request): RedirectResponse
    {
        $data = $this->validateDomain($request, true);
        $domain = new SubdomainDomain([
            'domain' => $data['domain'],
            'provider' => $data['provider'],
            'available' => $request->boolean('available', true),
        ]);
        $domain->setSecrets($this->secretsFrom($request, $data['provider'], []));

        if ($error = $this->tryConnection($domain)) {
            $this->alert->danger($error)->flash();

            return redirect()->route('admin.subdomains')->withInput();
        }
        $domain->save();
        $this->alert->success('The domain was added.')->flash();

        return redirect()->route('admin.subdomains');
    }

    public function updateDomain(Request $request, SubdomainDomain $domain): RedirectResponse
    {
        $data = $this->validateDomain($request, false);
        $domain->available = $request->boolean('available');
        if ($data['provider'] !== $domain->provider) {
            $domain->provider = $data['provider'];
            $domain->zone_id = null;
            $domain->setSecrets($this->secretsFrom($request, $data['provider'], []));
        } else {
            $domain->setSecrets($this->secretsFrom($request, $data['provider'], $domain->secrets()));
        }

        if ($error = $this->tryConnection($domain)) {
            $this->alert->danger($error)->flash();

            return redirect()->route('admin.subdomains');
        }
        $domain->save();
        $this->alert->success('The domain was saved.')->flash();

        return redirect()->route('admin.subdomains');
    }

    public function toggleDomain(SubdomainDomain $domain): RedirectResponse
    {
        $domain->forceFill(['available' => !$domain->available])->save();

        return redirect()->route('admin.subdomains');
    }

    public function testDomain(SubdomainDomain $domain): RedirectResponse
    {
        $error = $this->tryConnection($domain);
        $error
            ? $this->alert->danger($error)->flash()
            : $this->alert->success('The connection to ' . $domain->providerName() . ' works and the zone ' . $domain->domain . ' was found.')->flash();

        return redirect()->route('admin.subdomains');
    }

    public function deleteDomain(SubdomainDomain $domain): RedirectResponse
    {
        if ($domain->subdomains()->exists()) {
            $this->alert->danger('Remove the subdomains made under this domain first.')->flash();

            return redirect()->route('admin.subdomains');
        }
        $domain->delete();
        $this->alert->success('The domain was removed.')->flash();

        return redirect()->route('admin.subdomains');
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $request->validate([
            'blacklist' => ['nullable', 'string', 'max:10000'],
            'records' => ['required', 'string', 'max:20000'],
            'default_limit' => ['required', 'integer', 'min:0', 'max:100'],
        ]);
        $templates = json_decode((string) $request->input('records'), true);
        $this->service->saveSettings((string) $request->input('blacklist', ''), is_array($templates) ? $templates : [], (int) $request->input('default_limit'));
        $this->custom->setEnabled($request->boolean('user_domains'));
        $this->alert->success('The settings of the subdomains were saved.')->flash();

        return redirect()->route('admin.subdomains');
    }

    public function deleteSubdomain(Request $request, ServerSubdomain $subdomain): RedirectResponse
    {
        try {
            $this->service->delete($subdomain, $request->boolean('force'));
            $this->alert->success('The subdomain was removed.')->flash();
        } catch (DisplayException $exception) {
            $this->alert->danger($exception->getMessage() . ' Use "Remove anyway" to remove it from the panel only.')->flash();
        }

        return redirect()->route('admin.subdomains');
    }

    public function deleteCustom(ServerCustomDomain $custom): RedirectResponse
    {
        $custom->delete();
        $this->alert->success('The domain was removed.')->flash();

        return redirect()->route('admin.subdomains');
    }

    /**
     * The number of subdomains of one server, from its Build page. Empty means the default of the panel.
     */
    public function serverLimit(Request $request, Server $server): RedirectResponse
    {
        $request->validate(['subdomain_limit' => ['nullable', 'integer', 'min:0', 'max:100']]);
        $value = $request->input('subdomain_limit');
        $server->forceFill(['subdomain_limit' => ($value === null || $value === '') ? null : (int) $value])->saveQuietly();
        $this->alert->success('The subdomain limit was saved.')->flash();

        return redirect()->route('admin.servers.view.build', $server->id);
    }

    private function validateDomain(Request $request, bool $creating): array
    {
        $data = $request->validate([
            'domain' => $creating
                ? ['required', 'string', 'max:191', 'regex:/^(?=.{1,191}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', 'unique:subdomain_domains,domain']
                : ['nullable'],
            'provider' => ['required', 'string', 'in:' . implode(',', array_keys(SubdomainDomain::PROVIDERS))],
            'token' => ['nullable', 'string', 'max:500'],
            'key' => ['nullable', 'string', 'max:500'],
            'url' => ['nullable', 'url', 'max:500'],
            'server' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/'],
        ]);

        return array_merge($data, ['domain' => strtolower(trim((string) $request->input('domain')))]);
    }

    /**
     * The keys of the provider from the form. A field left empty keeps the key already saved.
     */
    private function secretsFrom(Request $request, string $provider, array $current): array
    {
        $fields = match ($provider) {
            'cloudflare' => ['token'],
            'bunny' => ['key'],
            'powerdns' => ['url', 'key', 'server'],
            default => [],
        };
        $secrets = [];
        foreach ($fields as $field) {
            $value = trim((string) $request->input($field, ''));
            $secrets[$field] = $value !== '' ? $value : ($current[$field] ?? '');
        }

        return $secrets;
    }

    private function tryConnection(SubdomainDomain $domain): ?string
    {
        try {
            (new DnsClient($domain))->test();

            return null;
        } catch (DisplayException $exception) {
            return $exception->getMessage();
        } catch (ConnectionException) {
            return $domain->providerName() . ' could not be reached.';
        }
    }
}
