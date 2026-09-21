<?php

namespace Pterodactyl\Http\Middleware;

use Closure;
use Carbon\Carbon;
use Pterodactyl\Models\User;
use Illuminate\Http\Request;
use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Notes that a signed-in person is using the panel, and on which server, for the "active now" list of the
 * administration.
 *
 * The panel and the dashboard call it all the time (the dashboard asks for the usage of its servers every few seconds),
 * so writing to the database each time would be a waste: a person is written at most every twenty seconds, or at once
 * when they move to another server. Only people signed in with their session are followed, not API keys, and whatever
 * goes wrong here never reaches the page.
 */
class TrackPresence
{
    private const EVERY_SECONDS = 20;

    /**
     * The route of the dashboard (the list of servers): being there means being on no server.
     */
    private const DASHBOARD_ROUTE = 'api:client.resources';

    public function __construct(private CacheRepository $cache)
    {
    }

    public function handle(Request $request, Closure $next): mixed
    {
        $response = $next($request);

        try {
            $this->track($request);
        } catch (\Throwable) {
            // Following people is never worth breaking a page.
        }

        return $response;
    }

    private function track(Request $request): void
    {
        $user = $request->user();
        if (!$user instanceof User || $request->bearerToken() !== null) {
            return;
        }

        $server = $request->route('server');
        $serverId = $server instanceof Server ? $server->id : null;
        // A request that is about no server in particular (the account, the permissions...) does not say where the
        // person is, except the one of the dashboard.
        $moved = $serverId !== null || $request->route()?->getName() === self::DASHBOARD_ROUTE;

        $key = 'presence:' . $user->id;
        $last = $this->cache->get($key);
        if (is_array($last) && (!$moved || $last['server'] === $serverId)) {
            return;
        }

        $this->cache->put($key, ['server' => $moved ? $serverId : ($last['server'] ?? null)], self::EVERY_SECONDS);

        $values = ['last_seen_at' => Carbon::now()];
        if ($moved) {
            $values['last_seen_server_id'] = $serverId;
        }
        DB::table('users')->where('id', $user->id)->update($values);
    }
}
