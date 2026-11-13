<?php

namespace Pterodactyl\Services\Mail;

use Illuminate\Support\Facades\Http;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerMailBox;
use Pterodactyl\Models\ServerMailDomain;
use Pterodactyl\Models\ServerWebDomain;

/**
 * Email on the own domain of a site, like Plesk: mailboxes (IMAP, SMTP and a webmail), with DKIM, SPF and DMARC.
 *
 * One mail server serves the whole panel. It runs on a machine of the administration (docker-mailserver for SMTP, IMAP
 * and the spam filter, Roundcube for the webmail), driven by install-assets/mail/pterodactyl-mail-agent: the agent asks
 * the panel for the mailboxes every few seconds, writes them in the files of the mail server, makes the DKIM key of each
 * new domain, and sends back the DKIM records and how full each mailbox is.
 *
 * The passwords are only kept as SHA512-CRYPT hashes, the form the mail server reads: the panel never stores them.
 */
class MailHosting
{
    public const DEFAULT_BOXES = 5;
    public const DEFAULT_QUOTA = 1024;

    // The agent reports every minute; it is shown offline after this many seconds without news.
    private const OFFLINE_AFTER = 300;

    public function __construct(private SettingsRepositoryInterface $settings)
    {
    }

    // -------------------------------------------------------------------------------------------------------------
    // Settings of the administration
    // -------------------------------------------------------------------------------------------------------------

    /**
     * The name of the mail server (mail.example.com): the MX of every domain, the IMAP and SMTP server of the apps.
     */
    public function hostname(): string
    {
        return (string) $this->settings->get('mail:hostname', '');
    }

    /**
     * The name of the webmail (webmail.example.com).
     */
    public function webmail(): string
    {
        return (string) $this->settings->get('mail:webmail', '');
    }

    public function maxBoxes(): int
    {
        return max(1, min(100, (int) $this->settings->get('mail:max_boxes', (string) self::DEFAULT_BOXES)));
    }

    public function quotaMb(): int
    {
        return max(50, min(102400, (int) $this->settings->get('mail:quota_mb', (string) self::DEFAULT_QUOTA)));
    }

    /**
     * @param array{hostname: string, webmail: string, max_boxes: int, quota_mb: int} $values
     */
    public function saveSettings(array $values): void
    {
        $this->settings->set('mail:hostname', strtolower(trim($values['hostname'])));
        $this->settings->set('mail:webmail', strtolower(trim($values['webmail'])));
        $this->settings->set('mail:max_boxes', (string) max(1, min(100, $values['max_boxes'])));
        $this->settings->set('mail:quota_mb', (string) max(50, min(102400, $values['quota_mb'])));
    }

    /**
     * Whether the clients can use email: the administration named the mail server.
     */
    public function enabled(): bool
    {
        return $this->hostname() !== '';
    }

    /**
     * A new key for the agent. Only its hash is kept: the key is shown once, in the install command.
     */
    public function newToken(): string
    {
        $token = 'pml_' . bin2hex(random_bytes(24));
        $this->settings->set('mail:token', hash('sha256', $token));

        return $token;
    }

    public function hasToken(): bool
    {
        return (string) $this->settings->get('mail:token', '') !== '';
    }

    public function checkToken(?string $token): bool
    {
        $hash = (string) $this->settings->get('mail:token', '');

        return $hash !== '' && is_string($token) && $token !== '' && hash_equals($hash, hash('sha256', $token));
    }

    /**
     * What the agent said last, with whether it is still talking to the panel.
     *
     * @return array<string, mixed>|null
     */
    public function agent(): ?array
    {
        $report = json_decode((string) $this->settings->get('mail:report', ''), true);
        if (!is_array($report)) {
            return null;
        }
        $at = (int) ($report['at'] ?? 0);
        $report['online'] = $at > time() - self::OFFLINE_AFTER;

        return $report;
    }

    // -------------------------------------------------------------------------------------------------------------
    // The domain and the mailboxes of a site
    // -------------------------------------------------------------------------------------------------------------

    /**
     * The own domain of the site that email can be turned on for: a domain of the client, whose DNS leads to the site.
     * A free subdomain of the panel cannot have email: its DNS belongs to the panel.
     */
    public function webDomainOf(Server $server): ?ServerWebDomain
    {
        return ServerWebDomain::query()->with('custom.allocation.node')->where('server_id', $server->id)
            ->whereNotNull('custom_domain_id')->orderBy('id')->get()
            ->first(fn (ServerWebDomain $d) => $d->ready());
    }

    public function domainOf(Server $server): ?ServerMailDomain
    {
        return ServerMailDomain::query()->where('server_id', $server->id)->orderBy('id')->first();
    }

    /**
     * Turns email on for the domain of the site. The mail server makes its DKIM key within a minute.
     *
     * @throws DisplayException
     */
    public function enable(Server $server): ServerMailDomain
    {
        if (!$this->enabled()) {
            throw new DisplayException('Email is not available on this panel yet.');
        }
        if ($existing = $this->domainOf($server)) {
            return $existing;
        }
        $web = $this->webDomainOf($server);
        if (!$web) {
            throw new DisplayException('Add your own domain in the Website tab first, and wait until it works.');
        }
        if (ServerMailDomain::query()->where('hostname', $web->hostname)->exists()) {
            throw new DisplayException('This domain already has email on another server.');
        }

        return ServerMailDomain::query()->create(['server_id' => $server->id, 'hostname' => $web->hostname]);
    }

    /**
     * Turns email off: every mailbox of the site is deleted, with its messages.
     */
    public function disable(Server $server): void
    {
        ServerMailDomain::query()->where('server_id', $server->id)->delete();
    }

    /**
     * @throws DisplayException
     */
    public function addBox(Server $server, string $local, string $password): ServerMailBox
    {
        $domain = $this->domainOf($server);
        if (!$domain) {
            throw new DisplayException('Turn email on first.');
        }
        $local = strtolower(trim($local));
        if (!preg_match('/^[a-z0-9](?:[a-z0-9._-]{0,62}[a-z0-9])?$/', $local) || str_contains($local, '..')) {
            throw new DisplayException('The name of the address can only hold letters, digits, dots, dashes and underscores.');
        }
        if (ServerMailBox::query()->where('server_id', $server->id)->count() >= $this->maxBoxes()) {
            throw new DisplayException('This site has reached its maximum of mailboxes.');
        }
        $address = $local . '@' . $domain->hostname;
        if (strlen($address) > 191 || ServerMailBox::query()->where('address', $address)->exists()) {
            throw new DisplayException('This address already exists.');
        }
        $this->checkPassword($password);

        return ServerMailBox::query()->create([
            'server_id' => $server->id,
            'mail_domain_id' => $domain->id,
            'local_part' => $local,
            'address' => $address,
            'password' => self::hash($password),
            'quota_mb' => $this->quotaMb(),
        ]);
    }

    /**
     * @throws DisplayException
     */
    public function setPassword(ServerMailBox $box, string $password): void
    {
        $this->checkPassword($password);
        $box->forceFill(['password' => self::hash($password)])->save();
    }

    /**
     * @throws DisplayException
     */
    private function checkPassword(string $password): void
    {
        if (mb_strlen($password) < 8 || mb_strlen($password) > 128) {
            throw new DisplayException('The password must have 8 to 128 characters.');
        }
        // The files of the mail server are line-based: a line break would end the line.
        if (preg_match('/[\x00-\x1f\x7f]/', $password)) {
            throw new DisplayException('The password cannot hold invisible characters.');
        }
    }

    /**
     * A SHA512-CRYPT hash, with the scheme in front, as Dovecot reads it.
     */
    public static function hash(string $password): string
    {
        $alphabet = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        $salt = '';
        for ($i = 0; $i < 16; ++$i) {
            $salt .= $alphabet[random_int(0, 63)];
        }

        return '{SHA512-CRYPT}' . crypt($password, '$6$' . $salt . '$');
    }

    // -------------------------------------------------------------------------------------------------------------
    // DNS
    // -------------------------------------------------------------------------------------------------------------

    /**
     * The records the client adds at their DNS provider, each with whether it is there (when $check is true).
     *
     * @return array<int, array{type: string, name: string, value: string, priority: int|null, status: string|null}>
     */
    public function records(ServerMailDomain $domain, bool $check = false): array
    {
        $host = $this->hostname();
        $name = $domain->hostname;
        $records = [
            ['type' => 'MX', 'name' => $name, 'value' => $host, 'priority' => 10, 'status' => null],
            ['type' => 'TXT', 'name' => $name, 'value' => 'v=spf1 mx ~all', 'priority' => null, 'status' => null],
            ['type' => 'TXT', 'name' => 'mail._domainkey.' . $name, 'value' => (string) $domain->dkim, 'priority' => null, 'status' => null],
            ['type' => 'TXT', 'name' => '_dmarc.' . $name, 'value' => 'v=DMARC1; p=quarantine; adkim=r; aspf=r', 'priority' => null, 'status' => null],
        ];
        if (!$check) {
            return $records;
        }

        $mx = $this->lookup($name, 'MX');
        $txt = $this->lookup($name, 'TXT');
        $dkim = $domain->dkim ? $this->lookup('mail._domainkey.' . $name, 'TXT') : null;
        $dmarc = $this->lookup('_dmarc.' . $name, 'TXT');

        $records[0]['status'] = self::state($mx, fn ($v) => rtrim(strtolower((string) preg_replace('/^\d+\s+/', '', $v)), '.') === $host);
        $records[1]['status'] = self::state(
            array_values(array_filter($txt ?? [], fn ($v) => str_starts_with(strtolower($v), 'v=spf1'))),
            fn ($v) => (bool) preg_match('/(^|\s)[+]?(mx|a:' . preg_quote($host, '/') . ')(\s|$)/i', $v),
            $txt === null
        );
        $records[2]['status'] = $domain->dkim === null ? 'pending' : self::state(
            $dkim,
            fn ($v) => self::dkimKey($v) !== '' && self::dkimKey($v) === self::dkimKey((string) $domain->dkim)
        );
        $records[3]['status'] = self::state(
            array_values(array_filter($dmarc ?? [], fn ($v) => str_starts_with(strtolower($v), 'v=dmarc1'))),
            fn ($v) => true,
            $dmarc === null
        );

        return $records;
    }

    /**
     * "ok" when one answer is right, "wrong" when there are answers but none is, "missing" when there is nothing, and
     * "unknown" when the DNS could not be asked.
     *
     * @param array<int, string>|null $answers
     */
    private static function state(?array $answers, callable $right, bool $failed = false): string
    {
        if ($answers === null || $failed) {
            return $answers === null ? 'unknown' : 'missing';
        }
        if ($answers === []) {
            return 'missing';
        }

        return collect($answers)->contains(fn ($v) => $right($v)) ? 'ok' : 'wrong';
    }

    private static function dkimKey(string $record): string
    {
        return preg_match('/p=([A-Za-z0-9+\/=\s]+)/', $record, $m) ? preg_replace('/\s+/', '', $m[1]) : '';
    }

    /**
     * The records of a name, asked to public resolvers over HTTPS (the resolver of the machine keeps old answers).
     * TXT records split in several strings are put back together.
     *
     * @return array<int, string>|null null when no resolver answered
     */
    public function lookup(string $name, string $type): ?array
    {
        $code = ['MX' => 15, 'TXT' => 16, 'A' => 1][$type] ?? 0;
        foreach (['https://cloudflare-dns.com/dns-query', 'https://dns.google/resolve'] as $resolver) {
            try {
                // Only "application/dns-json": Cloudflare refuses the request when "application/json" comes with it.
                $response = Http::withHeaders(['Accept' => 'application/dns-json'])
                    ->connectTimeout(2)->timeout(4)->get($resolver, ['name' => $name, 'type' => $type]);
            } catch (\Throwable) {
                continue;
            }
            if (!$response->successful() || !is_array($response->json())) {
                continue;
            }
            $out = [];
            foreach ((array) $response->json('Answer', []) as $answer) {
                if ((int) ($answer['type'] ?? 0) !== $code) {
                    continue;
                }
                $data = (string) ($answer['data'] ?? '');
                if ($type === 'TXT') {
                    // "v=DKIM1; k=rsa; " "p=MIIB..." -> one string.
                    preg_match_all('/"((?:[^"\\\\]|\\\\.)*)"/', $data, $parts);
                    $data = $parts[1] ? implode('', array_map('stripslashes', $parts[1])) : $data;
                }
                $out[] = trim($data);
            }

            return $out;
        }

        return null;
    }

    /**
     * The A records of several names (the name of the mail server and of the webmail, for the admin page).
     *
     * @param array<int, string> $names
     *
     * @return array<string, array<int, string>>
     */
    public function addresses(array $names): array
    {
        $names = array_values(array_filter(array_unique($names)));
        $out = [];
        foreach ($names as $n) {
            $out[$n] = $this->lookup($n, 'A') ?? [];
        }

        return $out;
    }

    // -------------------------------------------------------------------------------------------------------------
    // The agent
    // -------------------------------------------------------------------------------------------------------------

    /**
     * What the mail server must serve: its names, and the domains and mailboxes of the sites that are not suspended
     * and still own their domain.
     */
    public function feed(): array
    {
        // The own domains of the sites, as "server id|hostname": a domain given to another site stops getting mail here.
        $owned = ServerWebDomain::query()->whereNotNull('custom_domain_id')->get(['server_id', 'hostname'])
            ->mapWithKeys(fn (ServerWebDomain $w) => [$w->server_id . '|' . $w->hostname => true]);
        $domains = ServerMailDomain::query()->with('server')->get()->filter(
            fn (ServerMailDomain $d) => $d->server->status !== Server::STATUS_SUSPENDED && isset($owned[$d->server_id . '|' . $d->hostname])
        );
        $ids = $domains->pluck('id')->all();

        $boxes = $ids ? ServerMailBox::query()->whereIn('mail_domain_id', $ids)->orderBy('id')->get() : collect();

        return [
            'hostname' => $this->hostname(),
            'webmail' => $this->webmail(),
            'email' => (string) $this->settings->get('web:acme_email', ''),
            'domains' => $domains->pluck('hostname')->values()->all(),
            // Every mailbox that still exists, served or not: the agent only removes the messages of the others.
            'known' => ServerMailBox::query()->pluck('address')->values()->all(),
            'boxes' => $boxes->map(fn (ServerMailBox $b) => [
                'address' => $b->address,
                'password' => $b->password,
                'quota_mb' => $b->quota_mb,
            ])->values()->all(),
        ];
    }

    /**
     * What the agent sends back: its state, the DKIM record of each domain and how full each mailbox is.
     */
    public function saveReport(array $report): void
    {
        foreach ((array) ($report['dkim'] ?? []) as $hostname => $record) {
            if (is_string($record) && str_starts_with($record, 'v=DKIM1') && strlen($record) < 4096) {
                ServerMailDomain::query()->where('hostname', (string) $hostname)
                    ->where(fn ($q) => $q->whereNull('dkim')->orWhere('dkim', '!=', $record))->update(['dkim' => $record]);
            }
        }
        foreach (array_chunk((array) ($report['usage'] ?? []), 200, true) as $chunk) {
            foreach ($chunk as $address => $bytes) {
                if (is_numeric($bytes)) {
                    ServerMailBox::query()->where('address', (string) $address)->update(['used_bytes' => max(0, (int) $bytes)]);
                }
            }
        }

        $keep = array_intersect_key($report, array_flip(['version', 'running', 'certificate', 'webmail_certificate', 'outbound_25', 'ip', 'errors']));
        $keep['errors'] = array_slice(array_map(fn ($e) => mb_substr((string) $e, 0, 300), (array) ($keep['errors'] ?? [])), 0, 10);
        $keep['at'] = time();
        $this->settings->set('mail:report', (string) json_encode($keep));
    }
}
