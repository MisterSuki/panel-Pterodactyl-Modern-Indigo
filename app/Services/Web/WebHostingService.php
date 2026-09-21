<?php

namespace Pterodactyl\Services\Web;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\User;
use Pterodactyl\Models\WebAccount;
use Pterodactyl\Models\WebDomain;
use Pterodactyl\Models\WebPlan;
use Pterodactyl\Models\WebSite;
use Pterodactyl\Services\Servers\ServerDeletionService;
use Pterodactyl\Services\Servers\SuspensionService;
use Pterodactyl\Services\Users\UserCreationService;

/**
 * The web hosting: clients, their accounts and sites, the domains of each site, and the configuration of the web server
 * that leads each domain to its site. A site is a server of the panel (see WebProvisioner).
 */
class WebHostingService
{
    /**
     * Written as the reason when a hosting account is suspended, so that only these servers are given back when it is not.
     */
    public const SUSPENSION_REASON = 'Web hosting: the account is suspended.';

    /**
     * Endings that a domain cannot have: they are not on the Internet.
     */
    private const RESERVED_ENDINGS = ['local', 'localhost', 'internal', 'invalid', 'lan', 'home', 'corp', 'arpa'];

    /**
     * Names under the domain of the hosting that a client cannot pick: they are the ones people expect to be the hosting's.
     */
    private const RESERVED_LABELS = ['www', 'mail', 'smtp', 'imap', 'pop', 'pop3', 'ftp', 'sftp', 'ns', 'ns1', 'ns2', 'admin', 'administrator', 'panel', 'api', 'app', 'root', 'webmail', 'cpanel', 'plesk', 'support', 'billing', 'status', 'blog', 'shop', 'store', 'test', 'dev', 'staging', 'demo', 'localhost'];

    private DnsResolver $dns;

    public function __construct(
        private WebHostingSettings $settings,
        private WebProvisioner $provisioner,
        private UserCreationService $users,
        private SuspensionService $suspensions,
        private ServerDeletionService $deletion,
        private ProxyConfigBuilder $builder,
        ?DnsResolver $dns = null
    ) {
        $this->dns = $dns ?? new SystemDnsResolver();
    }

    // ---- clients, accounts and sites --------------------------------------------------------------------------

    /**
     * Makes a new client (a user of the panel, who gets an email to choose a password unless one is given), gives them a
     * hosting plan, and makes their first site.
     *
     * @param array<string, mixed> $user username, email, name_first, name_last, and optionally password and language
     *
     * @return array{0: User, 1: WebAccount, 2: WebSite}
     *
     * @throws DisplayException
     */
    public function createClient(array $user, WebPlan $plan, string $siteName, ?string $domain = null): array
    {
        $this->assertPlan($plan);
        $domain = $domain !== null && trim($domain) !== '' ? $this->normalise($domain) : null;
        if ($domain !== null) {
            $this->assertFreeDomain($domain);
        }

        $client = $this->users->handle($user);
        $account = $this->giveAccount($client, $plan);

        try {
            $site = $this->createSite($account, $siteName, $domain);
        } catch (DisplayException $exception) {
            // The client and their account exist and stay: the site can be made again from the administration.
            throw new DisplayException('The client ' . $client->username . ' was made, but their site was not: ' . $exception->getMessage());
        }

        return [$client, $account, $site];
    }

    /**
     * Gives a hosting plan to a person who already has an account on the panel.
     *
     * @throws DisplayException
     */
    public function giveAccount(User $user, WebPlan $plan): WebAccount
    {
        $this->assertPlan($plan);

        return WebAccount::query()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'max_sites' => $plan->max_sites,
            'max_domains' => $plan->max_domains,
            'status' => WebAccount::ACTIVE,
        ]);
    }

    /**
     * Makes a site: the server first, then its first domain (the one given, or a name under the base domain).
     *
     * @throws DisplayException
     */
    public function createSite(WebAccount $account, string $name, ?string $domain = null, ?string $php = null, bool $resolved = false): WebSite
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 80) {
            throw new DisplayException('The name of the site must be between 1 and 80 characters.');
        }
        if ($account->status !== WebAccount::ACTIVE) {
            throw new DisplayException('This hosting account is suspended.');
        }
        $plan = $account->plan;
        if (!$plan || !$plan->enabled) {
            throw new DisplayException('The plan of this account can no longer be used to make sites.');
        }
        if ($php !== null && $plan->imageFor($php) === null) {
            throw new DisplayException('This version of PHP is not part of the plan.');
        }
        $domain = $domain !== null && trim($domain) !== '' ? ($resolved ? strtolower(trim($domain)) : $this->normalise($domain)) : null;
        if ($domain !== null) {
            if ($resolved) {
                // A domain that resolveDomain() gave (a name of the hosting itself): it only has to be free.
                if (WebDomain::query()->where('domain', $domain)->exists()) {
                    throw new DisplayException('This domain is already used by a site.');
                }
            } else {
                $this->assertFreeDomain($domain);
            }
        }

        $site = DB::transaction(function () use ($account, $name, $plan, $php) {
            $locked = WebAccount::query()->whereKey($account->id)->lockForUpdate()->first();
            if (WebSite::query()->where('account_id', $locked->id)->count() >= $locked->max_sites) {
                throw new DisplayException('The plan allows ' . $locked->max_sites . ' site(s) and they are all made.');
            }

            return WebSite::query()->create([
                'account_id' => $locked->id,
                'name' => $name,
                'php_version' => $php ?? $plan->startingVersion(),
                'status' => WebSite::CREATING,
            ]);
        });

        try {
            $server = $this->provisioner->provision($account->user, $plan, $name, $site->php_version);
        } catch (\Throwable $exception) {
            report($exception);
            $site->delete();

            throw new DisplayException('The site could not be made (there may be no room on the servers). Nothing was kept, try again later.');
        }

        $site->update(['server_id' => $server->id, 'status' => WebSite::ACTIVE]);

        $primary = $domain ?? ($this->settings->autoSubdomain() ? $this->subdomainFor($name) : null);
        if ($primary !== null) {
            $this->attachDomain($site, $primary, false, true);
        }

        return $site->refresh();
    }

    /**
     * Stops all the sites of an account (the servers are suspended, nothing is deleted), or starts them again.
     */
    public function setAccountSuspended(WebAccount $account, bool $suspended): void
    {
        $account->update(['status' => $suspended ? WebAccount::SUSPENDED : WebAccount::ACTIVE]);

        foreach (WebSite::query()->where('account_id', $account->id)->whereNotNull('server_id')->get() as $site) {
            try {
                $server = Server::query()->without('allocation')->find($site->server_id);
                if (!$server) {
                    continue;
                }
                if ($suspended && !$server->isSuspended()) {
                    $this->suspensions->toggle($server, SuspensionService::ACTION_SUSPEND, ['reason' => self::SUSPENSION_REASON]);
                } elseif (!$suspended && $server->isSuspended()) {
                    $reason = optional(\Pterodactyl\Models\ServerSuspension::query()->where('server_id', $server->id)->first())->reason;
                    if ($reason === self::SUSPENSION_REASON) {
                        $this->suspensions->toggle($server, SuspensionService::ACTION_UNSUSPEND);
                    }
                }
            } catch (\Throwable $exception) {
                Log::warning('A site could not be suspended or given back.', ['site' => $site->id, 'error' => $exception->getMessage()]);
            }
        }
    }

    /**
     * Deletes a site with its server and its domains. The files and the databases of the server go with it.
     */
    public function deleteSite(WebSite $site): void
    {
        if ($site->server_id && ($server = Server::query()->find($site->server_id))) {
            $this->deletion->handle($server);
        }
        WebDomain::query()->where('site_id', $site->id)->delete();
        $site->delete();
    }

    /**
     * Changes the version of PHP of a site (it applies when the server starts again).
     *
     * @throws DisplayException
     */
    public function setPhpVersion(WebSite $site, string $version): WebSite
    {
        $plan = $site->account->plan;
        $image = $plan?->imageFor($version);
        if (!$image) {
            throw new DisplayException('This version of PHP is not part of the plan.');
        }
        $server = $site->server_id ? Server::query()->find($site->server_id) : null;
        if (!$server) {
            throw new DisplayException('This site has no server yet.');
        }

        if ($site->php_version !== $version) {
            $this->provisioner->applyImage($server, $image);
            $site->update(['php_version' => $version]);
        }

        return $site;
    }

    /**
     * Gives a person a hosting account with the plan and its first site, in one go: what happens when a web hosting plan
     * is bought. If the site cannot be made, the account is not kept either.
     *
     * @return array{0: WebAccount, 1: WebSite}
     *
     * @throws DisplayException
     */
    public function provisionForOrder(User $user, WebPlan $plan, string $siteName, ?string $domain): array
    {
        $account = $this->giveAccount($user, $plan);

        try {
            $site = $this->createSite($account, $siteName, $domain, null, true);
        } catch (\Throwable $exception) {
            $account->delete();

            throw $exception;
        }

        return [$account, $site];
    }

    /**
     * The domain a new site is going to have, checked before anything is made or paid: the one the buyer wrote, or a name
     * under the domain of the hosting (the one they picked, or one made from the name of the site) if that is switched on.
     *
     * @throws DisplayException
     */
    public function resolveDomain(?string $domain, ?string $label, string $siteName): ?string
    {
        $domain = trim((string) $domain);
        if ($domain !== '') {
            $domain = $this->normalise($domain);
            $this->assertFreeDomain($domain);

            return $domain;
        }

        if (!$this->settings->autoSubdomain()) {
            throw new DisplayException('Give the domain of your site.');
        }

        $label = strtolower(trim((string) $label));
        if ($label === '') {
            return $this->subdomainFor($siteName);
        }
        if (preg_match('/^[a-z0-9](?:[a-z0-9-]{0,38}[a-z0-9])?$/', $label) !== 1) {
            throw new DisplayException('The name can only have letters, digits and hyphens (40 characters at most), and cannot start or end with a hyphen.');
        }
        if (in_array($label, self::RESERVED_LABELS, true)) {
            throw new DisplayException('This name is reserved, choose another one.');
        }
        $candidate = $label . '.' . $this->settings->baseDomain();
        if (WebDomain::query()->where('domain', $candidate)->exists()) {
            throw new DisplayException('This name is already taken, choose another one.');
        }

        return $candidate;
    }

    // ---- domains ----------------------------------------------------------------------------------------------

    /**
     * Adds a domain to a site. It is served once its DNS leads to the web server; until then it is waiting.
     *
     * @throws DisplayException
     */
    public function addDomain(WebSite $site, string $domain, bool $includeWww = false): WebDomain
    {
        $domain = $this->normalise($domain);
        $this->assertFreeDomain($domain);

        $count = WebDomain::query()->where('site_id', $site->id)->count();
        if ($count >= $site->account->max_domains) {
            throw new DisplayException('The plan allows ' . $site->account->max_domains . ' domain(s) per site.');
        }

        return $this->attachDomain($site, $domain, $includeWww, $count === 0);
    }

    public function removeDomain(WebSite $site, WebDomain $domain): void
    {
        if ($domain->site_id !== $site->id) {
            throw new DisplayException('This domain is not on this site.');
        }
        $wasPrimary = $domain->is_primary;
        $domain->delete();

        if ($wasPrimary && ($next = WebDomain::query()->where('site_id', $site->id)->orderBy('id')->first())) {
            $next->update(['is_primary' => true]);
        }
    }

    /**
     * Checks where the DNS of a domain leads, and marks it verified if it leads to the web server.
     */
    public function verify(WebDomain $domain): bool
    {
        $ips = $this->settings->serverIps();
        $ok = $ips === [] || $this->leadsTo($domain->domain, $ips) && (!$domain->include_www || $this->leadsTo('www.' . $domain->domain, $ips));

        $domain->update([
            'status' => $ok ? WebDomain::VERIFIED : WebDomain::PENDING,
            'verified_at' => $ok ? ($domain->verified_at ?? now()) : null,
            'checked_at' => now(),
        ]);

        return $ok;
    }

    /**
     * Checks the domains that are waiting.
     *
     * @return int how many became verified
     */
    public function verifyPending(): int
    {
        $count = 0;
        foreach (WebDomain::query()->where('status', WebDomain::PENDING)->get() as $domain) {
            if ($this->verify($domain)) {
                ++$count;
            }
        }

        return $count;
    }

    // ---- the web server ---------------------------------------------------------------------------------------

    /**
     * The configuration of the web server: every verified domain of every site that is running and not suspended, led
     * to the address and the port of its server.
     */
    public function proxyConfig(): string
    {
        $entries = [];
        $sites = WebSite::query()->where('status', WebSite::ACTIVE)->whereNotNull('server_id')
            ->whereHas('account', fn ($query) => $query->where('status', WebAccount::ACTIVE))
            ->with(['domains' => fn ($query) => $query->where('status', WebDomain::VERIFIED)])->orderBy('id')->get();

        foreach ($sites as $site) {
            if ($site->domains->isEmpty() || !($target = $this->targetFor($site))) {
                continue;
            }
            $hosts = [];
            foreach ($site->domains as $domain) {
                $hosts[] = $domain->domain;
                if ($domain->include_www) {
                    $hosts[] = 'www.' . $domain->domain;
                }
            }
            $entries[] = ['hosts' => $hosts, 'target' => $target];
        }

        return $this->builder->build($entries);
    }

    /**
     * The address and the port at which the server of a site answers.
     */
    public function targetFor(WebSite $site): ?string
    {
        $server = Server::query()->with(['allocation', 'node'])->find($site->server_id);
        if (!$server || !$server->allocation) {
            return null;
        }

        $ip = (string) $server->allocation->ip;
        $host = in_array($ip, ['0.0.0.0', '::', ''], true) ? (string) ($server->node->fqdn ?? '') : $ip;
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $host = '[' . $host . ']';
        }

        return $host !== '' ? $host . ':' . $server->allocation->port : null;
    }

    // ---- helpers ----------------------------------------------------------------------------------------------

    /**
     * A domain written the way it is stored: lower case, without a dot at the end, and only if it can be a real domain.
     *
     * @throws DisplayException
     */
    public function normalise(string $domain): string
    {
        $domain = strtolower(trim($domain, " \t\n\r\0\x0B."));
        if (function_exists('idn_to_ascii') && preg_match('/[^\x00-\x7F]/', $domain) === 1) {
            $ascii = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            $domain = $ascii === false ? $domain : $ascii;
        }

        if (!ProxyConfigBuilder::validHost($domain) || filter_var($domain, FILTER_VALIDATE_IP) !== false) {
            throw new DisplayException('"' . Str::limit($domain, 60) . '" is not a valid domain name.');
        }
        $ending = substr($domain, (int) strrpos($domain, '.') + 1);
        if (in_array($ending, self::RESERVED_ENDINGS, true)) {
            throw new DisplayException('This domain name cannot be used.');
        }

        $panel = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        if ($panel !== '' && ($domain === $panel || $domain === 'www.' . $panel)) {
            throw new DisplayException('This domain name is the one of the panel itself and cannot be used for a site.');
        }

        return $domain;
    }

    /**
     * @throws DisplayException
     */
    private function assertFreeDomain(string $domain): void
    {
        if (WebDomain::query()->where('domain', $domain)->exists()) {
            throw new DisplayException('This domain is already used by a site.');
        }
        // Being a "www." name of a domain that is already there would serve the same name twice.
        if (str_starts_with($domain, 'www.') && WebDomain::query()->where('domain', substr($domain, 4))->where('include_www', true)->exists()) {
            throw new DisplayException('This domain is already used by a site.');
        }
        $base = $this->settings->baseDomain();
        if ($base !== null && $domain !== $base && str_ends_with($domain, '.' . $base)) {
            throw new DisplayException('Names under ' . $base . ' are given by the hosting itself.');
        }
    }

    /**
     * @throws DisplayException
     */
    private function assertPlan(WebPlan $plan): void
    {
        if (!$plan->enabled) {
            throw new DisplayException('This plan is not on offer.');
        }
    }

    /**
     * Adds a domain that has been checked as a name, and works out right away whether it can be served.
     */
    private function attachDomain(WebSite $site, string $domain, bool $includeWww, bool $primary): WebDomain
    {
        $base = $this->settings->baseDomain();
        $own = $base !== null && str_ends_with($domain, '.' . $base);

        $record = WebDomain::query()->create([
            'site_id' => $site->id,
            'domain' => $domain,
            'include_www' => $includeWww && !$own,
            'is_primary' => $primary,
            'status' => WebDomain::PENDING,
        ]);

        // The names that the hosting gives itself are always served (their DNS is the wildcard of the hosting).
        if ($own) {
            $record->update(['status' => WebDomain::VERIFIED, 'verified_at' => now(), 'checked_at' => now()]);
        } else {
            $this->verify($record);
        }

        return $record->refresh();
    }

    /**
     * The name under the base domain that a new site gets, if there is a base domain: made from the name of the site.
     */
    private function subdomainFor(string $name): ?string
    {
        $base = $this->settings->baseDomain();
        if ($base === null) {
            return null;
        }

        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower(Str::ascii($name))), '-');
        $slug = substr($slug !== '' ? $slug : 'site', 0, 40);
        if (in_array($slug, self::RESERVED_LABELS, true)) {
            $slug .= '-site';
        }
        $candidate = $slug . '.' . $base;
        for ($i = 2; WebDomain::query()->where('domain', $candidate)->exists(); ++$i) {
            $candidate = $slug . '-' . $i . '.' . $base;
        }

        return $candidate;
    }

    /**
     * @param array<int, string> $ips
     */
    private function leadsTo(string $host, array $ips): bool
    {
        return array_intersect($this->dns->addresses($host), $ips) !== [];
    }
}
