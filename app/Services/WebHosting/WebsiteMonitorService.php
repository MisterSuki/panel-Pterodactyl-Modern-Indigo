<?php

namespace Pterodactyl\Services\WebHosting;

use Carbon\CarbonInterval;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\WebsiteMonitor;
use Pterodactyl\Services\Servers\ServerWatcher;

/**
 * Checks every minute that the websites answer (their main domain), while their server runs: a site that does not
 * answer twice in a row sends the alert "down" (bell, phones, Discord, as the server wants), and "back" when it
 * answers again. Twice a day, the end of the HTTPS certificate is read: an alert when it is close.
 */
class WebsiteMonitorService
{
    // Checks failed in a row before the alert, seconds to wait for an answer, days left of a certificate to warn.
    public const FAILURES = 2;
    public const TIMEOUT = 10;
    public const SSL_WARN_DAYS = 10;

    public function __construct(private WebHostingService $web, private ServerWatcher $watcher)
    {
    }

    public function run(): int
    {
        $servers = Server::query()->whereHas('nest', fn ($q) => $q->where('name', WebHostingSetup::NEST))->get();
        $targets = [];
        foreach ($servers as $server) {
            /** @var WebsiteMonitor $monitor */
            $monitor = WebsiteMonitor::query()->firstOrNew(['server_id' => $server->id]);
            $url = $this->web->primaryUrl($server);
            if (!$url) {
                $monitor->forceFill(['url' => null, 'state' => 'no_domain', 'failures' => 0, 'down_since' => null, 'alerted' => false])->save();
                continue;
            }
            // A site whose server is stopped is not down: nothing is said.
            if (!$this->running($server)) {
                $monitor->forceFill(['url' => $url, 'state' => 'stopped', 'failures' => 0, 'down_since' => null, 'alerted' => false, 'last_checked_at' => now()])->save();
                continue;
            }
            $targets[] = [$server, $monitor, $url];
        }

        foreach (array_chunk($targets, 20) as $chunk) {
            $responses = Http::pool(fn (Pool $pool) => array_map(
                fn (array $target) => $pool->as((string) $target[0]->id)
                    ->timeout(self::TIMEOUT)->connectTimeout(5)
                    ->withUserAgent('Mozilla/5.0 (compatible; PanelMonitor/1.0)')
                    ->withOptions(['allow_redirects' => ['max' => 5]])
                    ->get($target[2]),
                $chunk
            ));
            foreach ($chunk as [$server, $monitor, $url]) {
                $this->record($server, $monitor, $url, $responses[(string) $server->id] ?? null);
            }
        }

        DB::table('website_monitor_samples')->where('created_at', '<', now()->subDays(8))->delete();

        return count($targets);
    }

    /**
     * Keeps the result of one check, and sends the alerts.
     */
    public function record(Server $server, WebsiteMonitor $monitor, string $url, mixed $response): void
    {
        $ok = $response instanceof Response && $response->status() < 500;
        $ms = $response instanceof Response && $response->transferStats ? (int) round($response->transferStats->getTransferTime() * 1000) : null;
        $reason = $ok ? null : ($response instanceof Response ? 'HTTP ' . $response->status() : $this->reason($response));

        DB::table('website_monitor_samples')->insert(['server_id' => $server->id, 'ok' => $ok, 'ms' => $ms, 'created_at' => now()]);

        $monitor->forceFill([
            'url' => $url,
            'status_code' => $response instanceof Response ? $response->status() : null,
            'response_ms' => $ms,
            'last_error' => $reason,
            'last_checked_at' => now(),
        ]);

        if ($ok) {
            if ($monitor->alerted && $monitor->down_since) {
                $this->watcher->alertWebsite($server, 'site_up', ['url' => $url, 'duration' => $this->duration($monitor->down_since->diffInSeconds(now()))]);
            }
            $monitor->forceFill(['state' => 'up', 'failures' => 0, 'down_since' => null, 'alerted' => false]);
        } else {
            $failures = $monitor->failures + 1;
            $monitor->forceFill(['state' => 'down', 'failures' => min(255, $failures), 'down_since' => $monitor->down_since ?? now()]);
            if ($failures >= self::FAILURES && !$monitor->alerted) {
                $this->watcher->alertWebsite($server, 'site_down', ['url' => $url, 'reason' => $reason]);
                $monitor->alerted = true;
            }
        }

        // The certificate, twice a day.
        if (str_starts_with($url, 'https://') && ($monitor->ssl_checked_at === null || $monitor->ssl_checked_at->lt(now()->subHours(12)))) {
            $host = (string) parse_url($url, PHP_URL_HOST);
            $end = $this->certificateEnd($host);
            $monitor->forceFill(['ssl_checked_at' => now(), 'ssl_expires_at' => $end]);
            if ($end !== null) {
                $days = (int) floor(now()->diffInSeconds($end, false) / 86400);
                if ($days <= self::SSL_WARN_DAYS && ($monitor->ssl_warned_at === null || $monitor->ssl_warned_at->lt(now()->subDay()))) {
                    $this->watcher->alertWebsite($server, 'ssl_expiring', ['url' => $url, 'date' => $end->format('d/m/Y'), 'days' => max(0, $days)]);
                    $monitor->ssl_warned_at = now();
                }
            }
        }

        $monitor->save();
    }

    /**
     * What the Website tab shows: the state, and the availability of the last day and week.
     */
    public function present(Server $server): array
    {
        /** @var WebsiteMonitor|null $monitor */
        $monitor = WebsiteMonitor::query()->find($server->id);
        $uptime = function (int $hours) use ($server): ?float {
            $row = DB::table('website_monitor_samples')->where('server_id', $server->id)->where('created_at', '>=', now()->subHours($hours))
                ->selectRaw('COUNT(*) as total, SUM(CASE WHEN ok THEN 1 ELSE 0 END) as up, AVG(ms) as ms')->first();

            return $row && (int) $row->total > 0 ? round((int) $row->up / (int) $row->total * 100, 2) : null;
        };
        $average = DB::table('website_monitor_samples')->where('server_id', $server->id)->where('created_at', '>=', now()->subDay())->where('ok', true)->avg('ms');

        return [
            'url' => $monitor?->url,
            'state' => $monitor->state ?? 'unknown',
            'status_code' => $monitor?->status_code,
            'response_ms' => $monitor?->response_ms,
            'average_ms' => $average !== null ? (int) round((float) $average) : null,
            'down_since' => $monitor?->down_since?->toAtomString(),
            'last_error' => $monitor?->last_error,
            'last_checked_at' => $monitor?->last_checked_at?->toAtomString(),
            'ssl_expires_at' => $monitor?->ssl_expires_at?->toAtomString(),
            'uptime_24h' => $uptime(24),
            'uptime_7d' => $uptime(24 * 7),
        ];
    }

    /**
     * When the HTTPS certificate of a domain ends (null when it cannot be read).
     */
    public function certificateEnd(string $host): ?\Illuminate\Support\Carbon
    {
        if ($host === '') {
            return null;
        }
        $context = stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            'verify_peer' => false,
            'verify_peer_name' => false,
            'SNI_enabled' => true,
            'peer_name' => $host,
        ]]);
        $client = @stream_socket_client('ssl://' . $host . ':443', $errno, $error, 8, STREAM_CLIENT_CONNECT, $context);
        if (!$client) {
            return null;
        }
        $certificate = stream_context_get_params($client)['options']['ssl']['peer_certificate'] ?? null;
        fclose($client);
        $info = $certificate ? openssl_x509_parse($certificate) : false;

        return is_array($info) && isset($info['validTo_time_t']) ? \Illuminate\Support\Carbon::createFromTimestamp((int) $info['validTo_time_t']) : null;
    }

    /**
     * The server runs: the watch of the servers wrote a sample of it in the last minutes (one every 5 minutes).
     */
    private function running(Server $server): bool
    {
        return DB::table('server_resource_samples')->where('server_id', $server->id)->where('created_at', '>=', now()->subMinutes(11))->exists();
    }

    /**
     * Why a site did not answer, in a few words.
     */
    private function reason(mixed $error): string
    {
        $message = $error instanceof \Throwable ? $error->getMessage() : '';

        return match (true) {
            str_contains($message, 'timed out') || str_contains($message, 'Operation timed out') => 'no answer in ' . self::TIMEOUT . ' s',
            str_contains($message, 'resolve host') => 'domain not found (DNS)',
            str_contains($message, 'Connection refused') => 'connection refused',
            str_contains($message, 'SSL') || str_contains($message, 'certificate') => 'HTTPS error',
            default => 'unreachable',
        };
    }

    private function duration(float|int $seconds): string
    {
        return CarbonInterval::seconds(max(60, (int) $seconds))->cascade()->forHumans(['short' => true, 'parts' => 2]);
    }
}
