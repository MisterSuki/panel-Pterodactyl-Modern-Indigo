<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Pterodactyl\Models\Server;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Services\Servers\FiveMStatus;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Servers\GetServerRequest;

class FiveMController extends ClientApiController
{
    public function __construct(private FiveMStatus $status)
    {
        parent::__construct();
    }

    /**
     * The number of connected players and the txAdmin address of a FiveM server. Anyone who can open
     * the server's console can see them. For any other kind of server nothing is contacted.
     */
    public function __invoke(GetServerRequest $request, Server $server): JsonResponse
    {
        $this->status->prepare($server);

        return new JsonResponse(['object' => 'fivem_status', 'attributes' => $this->status->status($server)]);
    }
}
