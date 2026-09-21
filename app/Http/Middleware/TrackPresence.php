<?php

namespace Pterodactyl\Http\Middleware;

use Closure;
use Carbon\Carbon;
use Pterodactyl\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Notes that a signed-in person is using the panel, for the "active now" list of the administration.
 *
 * The dashboard calls the panel all the time, so writing to the database each time would be a waste: a person is
 * written at most every twenty seconds. Which page they are on is told by the dashboard itself (see
 * PresenceController); here only the pages of the administration are known, and they are noted at once when the
 * person arrives on them. Only people signed in with their session are followed, not API keys, and whatever goes
 * wrong here never reaches the page.
 */
class TrackPresence
{
    private const EVERY_SECONDS = 20;

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

        $page = $request->is('admin', 'admin/*') ? 'admin' : null;

        $key = 'presence:' . $user->id;
        $last = $this->cache->get($key);
        if (is_array($last) && ($page === null || ($last['page'] ?? null) === $page)) {
            return;
        }

        $this->cache->put($key, ['page' => $page ?? ($last['page'] ?? null)], self::EVERY_SECONDS);

        $values = ['last_seen_at' => Carbon::now()];
        if ($page !== null) {
            $values['last_seen_page'] = $page;
            $values['last_seen_server_id'] = null;
        }
        DB::table('users')->where('id', $user->id)->update($values);
    }
}
