<?php

namespace Pterodactyl\Http\Controllers\Api\Client;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Pterodactyl\Models\Server;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Services\Presence\PageResolver;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * The dashboard tells here, every half minute while its page is on screen, which page the person is on. That is what
 * the "active now" list of the administration shows.
 */
class PresenceController extends ClientApiController
{
    public function __construct(private PageResolver $pages, private CacheRepository $cache)
    {
        parent::__construct();
    }

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate(['path' => 'required|string|max:300']);

        $user = $request->user();
        $where = $this->pages->resolve((string) parse_url((string) $request->input('path'), PHP_URL_PATH));

        // The server is only kept if the person can open it: the list of the administration never names a server
        // that was not theirs to begin with.
        $serverId = null;
        if ($where['server'] !== null) {
            $query = $user->root_admin ? Server::query()->without('allocation') : $user->accessibleServers()->without('allocation');
            $serverId = $query->where('servers.uuidShort', $where['server'])->value('servers.id');
        }

        DB::table('users')->where('id', $user->id)->update([
            'last_seen_at' => Carbon::now(),
            'last_seen_page' => $where['page'],
            'last_seen_server_id' => $serverId,
        ]);
        // What the presence middleware keeps, so it does not write the same thing again a moment later.
        $this->cache->put('presence:' . $user->id, ['page' => $where['page']], 20);

        return new JsonResponse(['ok' => true]);
    }
}
