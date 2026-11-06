<?php

namespace Pterodactyl\Services\WebHosting;

use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Models\Database;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerWebDomain;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;
use Pterodactyl\Services\Databases\DatabaseManagementService;
use Pterodactyl\Services\Databases\DeployServerDatabaseService;

/**
 * Installs a CMS in a site, in one click. The panel creates the database and leaves a file in the site with what to
 * install; web-install, in the container, does the work and writes how it goes in another file.
 */
class WebInstaller
{
    private const JOB = '.web/install.json';

    private const STATUS = '.web/install.status';

    // Past this, an installation that says nothing has stopped (the site was restarted during it).
    private const STALE_SECONDS = 900;

    // The address of the database as seen from a container, when the panel knows it as "this machine".
    private const DOCKER_GATEWAY = '172.18.0.1';

    public const APPS = [
        'wordpress' => ['name' => 'WordPress', 'description' => 'Blogs and websites: the most used CMS in the world.', 'account' => true],
        'joomla' => ['name' => 'Joomla', 'description' => 'Complete CMS for websites and portals.', 'account' => true],
        'prestashop' => ['name' => 'PrestaShop', 'description' => 'Online shop.', 'account' => true],
        'nextcloud' => ['name' => 'Nextcloud', 'description' => 'Your own cloud: files, calendar, contacts. Needs 512 MB of PHP memory.', 'account' => true],
        'dolibarr' => ['name' => 'Dolibarr', 'description' => 'ERP and CRM: customers, invoices, stock and accounting.', 'account' => true],
        'clientxcms' => ['name' => 'ClientXCMS', 'description' => 'Billing for hosting companies. Needs PHP 8.3 or newer and your ClientXCMS licence, entered on its own page at the end.', 'account' => false],
        'whmcs' => ['name' => 'WHMCS', 'description' => 'Billing for hosting companies. Needs your licence: first send your WHMCS zip file to the main folder of the site, next to public_html.', 'account' => false],
        'drupal' => ['name' => 'Drupal', 'description' => 'Powerful CMS. The last step is done on its own page, with the database shown in the Databases tab.', 'account' => false],
    ];

    public function __construct(
        private DaemonFileRepository $files,
        private DaemonServerRepository $daemon,
        private DeployServerDatabaseService $databases,
        private DatabaseManagementService $management,
        private WebHostingService $web,
        private Encrypter $encrypter,
    ) {
    }

    /**
     * @param array{app: string, title?: string|null, admin_user?: string|null, admin_password?: string|null, admin_email?: string|null, locale: string} $data
     *
     * @throws DisplayException
     */
    public function start(Server $server, array $data): void
    {
        $state = (string) ($this->daemon->setServer($server)->getDetails()['state'] ?? '');
        if ($state !== 'running') {
            throw new DisplayException('Start the site first: the installation runs inside it.');
        }
        if (($this->status($server)['state'] ?? null) === 'running') {
            throw new DisplayException('An installation is already running on this site.');
        }

        $url = $this->siteUrl($server);
        if ($data['app'] === 'whmcs') {
            $this->requireWhmcsFile($server);
        }
        $database = $this->database($server);

        $this->files->setServer($server)->putContent(self::STATUS, (string) json_encode([
            'state' => 'running', 'app' => $data['app'], 'step' => 'Waiting for the site', 'url' => '', 'admin_url' => '', 'at' => time(),
        ]));
        $this->files->putContent(self::JOB, (string) json_encode([
            'app' => $data['app'],
            'url' => $url,
            'domain' => (string) parse_url($url, PHP_URL_HOST),
            'title' => $data['title'] ?? $server->name,
            'admin_user' => $data['admin_user'] ?? '',
            'admin_password' => $data['admin_password'] ?? '',
            'admin_email' => $data['admin_email'] ?? '',
            'locale' => $data['locale'],
            'db' => $database,
        ]));
    }

    /**
     * How the last installation went, or null when there was none.
     */
    public function status(Server $server): ?array
    {
        try {
            $status = json_decode($this->files->setServer($server)->getContent(self::STATUS, 4096), true);
        } catch (\Throwable) {
            return null;
        }
        if (!is_array($status) || !isset(self::APPS[$status['app'] ?? ''])) {
            return null;
        }
        if (($status['state'] ?? '') === 'running' && time() - (int) ($status['at'] ?? 0) > self::STALE_SECONDS) {
            $status['state'] = 'failed';
            $status['step'] = 'The installation stopped: it was interrupted.';
        }

        return [
            'state' => (string) ($status['state'] ?? ''),
            'app' => (string) $status['app'],
            'step' => mb_substr((string) ($status['step'] ?? ''), 0, 300),
            'url' => $this->link($status['url'] ?? ''),
            'admin_url' => $this->link($status['admin_url'] ?? ''),
        ];
    }

    /**
     * WHMCS only downloads from the account of its licence: nothing is removed until the client has sent the file.
     *
     * @throws DisplayException
     */
    private function requireWhmcsFile(Server $server): void
    {
        foreach ($this->files->setServer($server)->getDirectory('/') as $file) {
            if (preg_match('/^whmcs.*\.zip$/i', (string) ($file['name'] ?? '')) === 1) {
                return;
            }
        }

        throw new DisplayException('Send your WHMCS zip file to the main folder of the site first.');
    }

    /**
     * The address the CMS is set up for. A CMS keeps it in its database, so it must be the final one: with the address
     * in http while the machine sends visitors to https, each one would redirect to the other forever.
     *
     * @throws DisplayException
     */
    private function siteUrl(Server $server): string
    {
        $domains = $this->web->domainsOf($server)->filter(fn (ServerWebDomain $d) => $d->ready());
        if ($domains->isEmpty()) {
            throw new DisplayException('Add a domain to the site first: the CMS is set up for its address.');
        }
        $domain = $domains->firstWhere('ssl_status', ServerWebDomain::SSL_ACTIVE);
        if ($domain === null && $domains->contains('ssl_status', ServerWebDomain::SSL_PENDING)) {
            throw new DisplayException('The HTTPS certificate of the site is not ready yet. Try again in a minute.');
        }

        return ($domain ?? $domains->first())->url();
    }

    /**
     * The status file is in the folder of the client: only a real address goes back to the page.
     */
    private function link(mixed $url): ?string
    {
        return is_string($url) && preg_match('#^https?://#i', $url) === 1 ? mb_substr($url, 0, 500) : null;
    }

    /**
     * A new database for the CMS, in the limit of the server. One made by an earlier installation goes first: the site
     * it belonged to was just replaced, and another try after a failure must not run out of room.
     *
     * @return array{host: string, port: int, name: string, user: string, password: string}
     *
     * @throws DisplayException
     */
    private function database(Server $server): array
    {
        foreach ($server->databases()->get() as $old) {
            if (preg_match('/^s\d+_site[0-9a-f]{5}$/', $old->database) === 1) {
                $this->management->delete($old);
            }
        }

        $database = DB::transaction(function () use ($server) {
            if ($server->databases()->lockForUpdate()->count() >= $server->database_limit) {
                throw new DisplayException('The CMS needs a database, and this site has no room for one. Remove one, or ask for a higher limit.');
            }

            return $this->databases->handle($server, ['database' => 'site' . substr(bin2hex(random_bytes(3)), 0, 5), 'remote' => '%']);
        });

        return $this->connection($database);
    }

    /**
     * @return array{host: string, port: int, name: string, user: string, password: string}
     */
    private function connection(Database $database): array
    {
        $host = $database->host->host;

        return [
            'host' => in_array($host, ['127.0.0.1', 'localhost', '::1'], true) ? self::DOCKER_GATEWAY : $host,
            'port' => (int) $database->host->port,
            'name' => $database->database,
            'user' => $database->username,
            'password' => $this->encrypter->decrypt($database->password),
        ];
    }
}
