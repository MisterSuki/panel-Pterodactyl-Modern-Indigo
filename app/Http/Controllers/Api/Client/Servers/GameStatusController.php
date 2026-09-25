<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Pterodactyl\Models\Server;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Services\Servers\GameStatus;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Servers\GetServerRequest;

class GameStatusController extends ClientApiController
{
    public function __construct(private GameStatus $status)
    {
        parent::__construct();
    }

    /**
     * The number of players of a game server that answers the Steam query. Anyone who can open the console can see it.
     * For a server that is not made from a Steam egg nothing is contacted.
     */
    public function __invoke(GetServerRequest $request, Server $server): JsonResponse
    {
        return new JsonResponse(['object' => 'game_status', 'attributes' => $this->status->status($server)]);
    }
}
