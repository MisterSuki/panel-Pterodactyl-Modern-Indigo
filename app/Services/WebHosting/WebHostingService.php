<?php

namespace Pterodactyl\Services\WebHosting;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Collection;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerCustomDomain;
use Pterodactyl\Models\ServerWebDomain;
use Pterodactyl\Models\SubdomainDomain;
use Pterodactyl\Services\Notifications\AlertTexts;
use Pterodactyl\Services\Subdomains\CustomDomainService;
use Pterodactyl\Services\Subdomains\SubdomainService;

/**
 * The domains of the websites, and what the web agent of a node needs: which domain goes to which container.
 *
 * A website is a server made from an egg that has the "web" feature. The visitors never see its port: the agent sends
 * the domain (ports 80 and 443 of the machine) to the port of the container, and closes that port to the outside.
 */
class WebHostingService
{
    public const FEATURE = 'web';

    // The number of domains of one site (its own domains and free subdomains together), unless the administration
    // sets another one. The www. of a domain comes with it and does not count.
    public const MAX_DOMAINS = 1;

    private const AGENT_SECONDS = 180;

    public function __construct(
        private CacheRepository $cache,
        private SettingsRepositoryInterface $settings,
        private CustomDomainService $custom,
        private SubdomainService $subdomains,
    ) {
    }

    public static function isWebsite(Server $server): bool
    {
        return in_array(self::FEATURE, (array) $server->egg?->inherit_features, true);
    }

    public function acmeEmail(): string
    {
        return (string) $this->settings->get('web:acme_email', '');
    }

    public function setAcmeEmail(string $email): void
    {
        $this->settings->set('web:acme_email', $email);
    }

    /**
     * How many domains one site can have.
     */
    public function maxDomains(): int
    {
        $max = (int) $this->settings->get('web:max_domains', (string) self::MAX_DOMAINS);

        return max(1, min(50, $max));
    }

    public function setMaxDomains(int $max): void
    {
        $this->settings->set('web:max_domains', (string) max(1, min(50, $max)));
    }

    /**
     * The own domain of a site. The name is checked like every own domain of the panel, and its DNS must lead to the
     * machine of the server before anything is done with it.
     *
     * @throws DisplayException
     */
    public function addOwnDomain(Server $server, string $hostname): ServerWebDomain
    {
        $this->checkRoom($server);
        $hostname = $this->custom->checkHostname($hostname);
        $this->refuseNodeName($server, $hostname);

        $custom = new ServerCustomDomain([
            'server_id' => $server->id,
            'hostname' => $hostname,
            'allocation_id' => $server->allocation_id,
        ]);
        $custom->save();

        $domain = new ServerWebDomain(['server_id' => $server->id, 'hostname' => $hostname, 'custom_domain_id' => $custom->id]);
        $domain->save();
        $this->check($domain);

        return $domain;
    }

    /**
     * A subdomain of a domain of the panel: its DNS record is made by the panel, so it works right away.
     *
     * @throws DisplayException
     */
    public function addSubdomain(Server $server, SubdomainDomain $zone, string $name): ServerWebDomain
    {
        $this->checkRoom($server);
        $subdomain = $this->subdomains->create($server, $zone, $name, $server->allocation, null);

        $domain = new ServerWebDomain(['server_id' => $server->id, 'hostname' => $subdomain->fqdn(), 'subdomain_id' => $subdomain->id]);
        $domain->save();

        return $domain;
    }

    /**
     * Looks again at the DNS of a domain: the name itself, and whether www. leads to the same place.
     */
    public function check(ServerWebDomain $domain): bool
    {
        $domain->loadMissing('custom.allocation.node');
        if ($domain->custom === null) {
            return true;
        }
        $ok = $this->custom->verify($domain->custom);
        $domain->setRelation('custom', $domain->custom->refresh());

        $www = false;
        if ($ok) {
            $wanted = $this->custom->addressesOf($domain->hostname);
            $www = !empty(array_intersect($this->custom->addressesOf('www.' . $domain->hostname), $wanted));
        }
        if ($www !== $domain->www) {
            $domain->forceFill(['www' => $www, 'ssl_status' => ServerWebDomain::SSL_PENDING, 'ssl_error' => null])->save();
        }

        return $ok;
    }

    /**
     * What is wrong with the DNS of a domain that does not lead to the server, said so the client can fix it.
     */
    public function dnsProblem(ServerWebDomain $domain, ?string $language): string
    {
        $found = $this->custom->addressesOf($domain->hostname);
        $wanted = $this->records($domain)[0]['content'] ?? '';
        $key = empty($found) ? 'web_dns_missing' : 'web_dns_elsewhere';

        return implode(' ', AlertTexts::get($key, $language, [
            'name' => $domain->hostname,
            'found' => implode(', ', $found),
            'wanted' => $wanted,
        ]));
    }

    /**
     * The temporary address of a site, while no domain leads to it: its port is only closed once it has one.
     */
    public function previewUrl(Server $server): ?string
    {
        if ($this->primaryUrl($server) !== null || $server->allocation === null) {
            return null;
        }
        try {
            [, $address] = $this->subdomains->address($server->allocation);
        } catch (DisplayException) {
            return null;
        }

        return 'http://' . (str_contains($address, ':') ? "[$address]" : $address) . ':' . $server->allocation->port;
    }

    /**
     * @throws DisplayException
     */
    public function remove(ServerWebDomain $domain): void
    {
        $domain->loadMissing(['subdomain.domain', 'custom']);
        // The row of the domain goes with what it comes from.
        if ($domain->subdomain !== null) {
            $this->subdomains->delete($domain->subdomain);
        } elseif ($domain->custom !== null) {
            $domain->custom->delete();
        } else {
            $domain->delete();
        }
    }

    /**
     * What to create in the DNS of an own domain.
     *
     * @return array<int, array{type: string, name: string, content: string}>
     */
    public function records(ServerWebDomain $domain): array
    {
        if ($domain->custom === null) {
            return [];
        }
        $address = $this->custom->records($domain->custom)[0] ?? null;

        // www is the same record as the name itself: a client finds two identical lines simpler than a CNAME.
        return $address === null ? [] : [
            $address,
            ['name' => 'www.' . $domain->hostname] + $address,
        ];
    }

    /**
     * The address of the site, for the panel to show and for the first settings of a CMS: its best domain, or else its
     * port while it has none.
     */
    public function primaryUrl(Server $server): ?string
    {
        $domains = ServerWebDomain::query()->with('custom')->where('server_id', $server->id)->orderBy('id')->get()
            ->filter(fn (ServerWebDomain $d) => $d->ready());
        $domain = $domains->firstWhere('ssl_status', ServerWebDomain::SSL_ACTIVE) ?? $domains->first();

        return $domain?->url();
    }

    /**
     * Everything the web agent of a node has to know.
     */
    public function feed(Node $node): array
    {
        $domains = ServerWebDomain::query()
            ->with(['custom', 'server.allocation'])
            ->whereHas('server', fn ($q) => $q->where('node_id', $node->id))
            ->get()
            ->filter(fn (ServerWebDomain $d) => $d->ready() && $d->server->allocation !== null);

        return [
            'ok' => true,
            'email' => $this->acmeEmail(),
            'sites' => $domains->map(fn (ServerWebDomain $d) => [
                'hostname' => $d->hostname,
                'aliases' => $d->www ? ['www.' . $d->hostname] : [],
                'upstream' => $this->upstream($d->server->allocation),
                'max_body' => $this->maxBody($d->server),
                'suspended' => $d->server->status !== null,
            ])->values()->all(),
            // Once a site has its domain, nobody goes through its port any more.
            'hidden_ports' => $domains->map(fn (ServerWebDomain $d) => (int) $d->server->allocation->port)->unique()->values()->all(),
        ];
    }

    /**
     * What the agent found out. Only the domains of its own node can be touched.
     *
     * @param array<int, array{hostname?: string, ssl?: string, error?: string|null}> $reports
     */
    public function saveReport(Node $node, array $reports, string $version): void
    {
        $this->cache->put('web-agent:' . $node->id, ['at' => time(), 'version' => substr($version, 0, 16)], 86400);

        $domains = ServerWebDomain::query()->whereHas('server', fn ($q) => $q->where('node_id', $node->id))->get()->keyBy('hostname');
        foreach ($reports as $report) {
            $domain = $domains[(string) ($report['hostname'] ?? '')] ?? null;
            $status = (string) ($report['ssl'] ?? '');
            if ($domain === null || !in_array($status, [ServerWebDomain::SSL_PENDING, ServerWebDomain::SSL_ACTIVE, ServerWebDomain::SSL_FAILED], true)) {
                continue;
            }
            $error = isset($report['error']) ? mb_substr((string) $report['error'], 0, 500) : null;
            if ($domain->ssl_status !== $status || $domain->ssl_error !== $error) {
                $domain->forceFill(['ssl_status' => $status, 'ssl_error' => $error])->save();
            }
        }
    }

    /**
     * @return array{at: int, version: string, online: bool}|null
     */
    public function agentOf(Node $node): ?array
    {
        $agent = $this->cache->get('web-agent:' . $node->id);

        return is_array($agent) ? $agent + ['online' => time() - (int) $agent['at'] < self::AGENT_SECONDS] : null;
    }

    /**
     * @return Collection<int, ServerWebDomain>
     */
    public function domainsOf(Server $server): Collection
    {
        return ServerWebDomain::query()->with('custom.allocation.node')->where('server_id', $server->id)->orderBy('id')->get()->toBase();
    }

    /**
     * @throws DisplayException
     */
    private function checkRoom(Server $server): void
    {
        $max = $this->maxDomains();
        if (ServerWebDomain::query()->where('server_id', $server->id)->count() >= $max) {
            throw new DisplayException($max === 1
                ? 'A site can have only one domain. Remove the current one to use another.'
                : 'This site has reached the maximum of domains.');
        }
    }

    /**
     * A site must not take the address of a node: the agent would put it in front of Wings.
     *
     * @throws DisplayException
     */
    private function refuseNodeName(Server $server, string $hostname): void
    {
        foreach (Node::query()->pluck('fqdn') as $fqdn) {
            if (strtolower((string) $fqdn) === $hostname) {
                throw new DisplayException('This domain is the one of a node of the panel.');
            }
        }
    }

    private function upstream(Allocation $allocation): string
    {
        $ip = in_array($allocation->ip, ['0.0.0.0', '::'], true) ? '127.0.0.1' : $allocation->ip;

        return $ip . ':' . $allocation->port;
    }

    /**
     * The largest request of the site: what the site itself accepts, so the machine does not refuse it first.
     */
    private function maxBody(Server $server): int
    {
        $value = $server->variables()->where('env_variable', 'UPLOAD_MAX_MB')->get()->first();
        $size = (int) ($value?->server_value ?? $value?->default_value ?? 0);

        return $size > 0 ? min($size + 1, 10240) : 256;
    }
}
