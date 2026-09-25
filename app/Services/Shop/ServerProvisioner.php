<?php

namespace Pterodactyl\Services\Shop;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Objects\DeploymentObject;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ShopOffer;
use Pterodactyl\Models\User;
use Pterodactyl\Services\Servers\ServerCreationService;

/**
 * Makes the server of an offer for the person who bought it. The node and the port are chosen by the panel itself, among
 * the nodes of the location of the offer that still have room (the same automatic deployment as the Application API).
 */
class ServerProvisioner
{
    public function __construct(private ServerCreationService $creation)
    {
    }

    /**
     * @throws \Throwable when the server cannot be made (no room, no free port, the node does not answer...)
     */
    public function provision(User $user, ShopOffer $offer): Server
    {
        $egg = Egg::query()->with('variables')->findOrFail($offer->egg_id);

        // Every variable of the egg gets its default value, and the offer can set some itself.
        $environment = [];
        foreach ($egg->variables as $variable) {
            $environment[$variable->env_variable] = (string) $variable->default_value;
        }
        foreach ($offer->environment ?? [] as $name => $value) {
            if (array_key_exists($name, $environment)) {
                $environment[$name] = (string) $value;
            }
        }

        $data = [
            'name' => Str::limit($offer->name . ' - ' . $user->username, 60, ''),
            'description' => 'Bought in the shop',
            'owner_id' => $user->id,
            'egg_id' => $egg->id,
            'nest_id' => $egg->nest_id,
            'memory' => $offer->memory,
            'swap' => 0,
            'disk' => $offer->disk,
            'io' => 500,
            'cpu' => $offer->cpu,
            'threads' => null,
            'oom_disabled' => true,
            'database_limit' => $offer->database_limit,
            'allocation_limit' => $offer->allocation_limit,
            'backup_limit' => $offer->backup_limit,
            'startup' => $egg->startup,
            'image' => (string) Arr::first($egg->docker_images ?? []),
            'environment' => $environment,
            'skip_scripts' => false,
            'start_on_completion' => true,
        ];

        $deployment = (new DeploymentObject())->setLocations([$offer->location_id])->setDedicated(false)->setPorts([]);

        $server = $this->creation->handle($data, $deployment);
        $this->addTxAdminPort($server, $egg);

        return $server;
    }

    /**
     * Makes a server a client built themselves: an egg and the resources they chose, placed among the locations given
     * (or every location when none is given).
     *
     * @param array{memory: int, disk: int, cpu: int, database_limit: int, allocation_limit: int, backup_limit: int} $limits
     * @param array<int, int>                                                                                          $locationIds
     *
     * @throws \Throwable
     */
    public function provisionCustom(User $user, int $eggId, array $limits, string $name, array $locationIds, array $variables = []): Server
    {
        $egg = Egg::query()->with('variables')->findOrFail($eggId);

        $environment = [];
        foreach ($egg->variables as $variable) {
            $environment[$variable->env_variable] = (string) $variable->default_value;
        }
        // What the client filled in (a FiveM licence...) overrides the defaults, only for variables the egg knows.
        foreach ($variables as $name2 => $value) {
            if (array_key_exists($name2, $environment)) {
                $environment[$name2] = (string) $value;
            }
        }

        $data = [
            'name' => Str::limit($name . ' - ' . $user->username, 60, ''),
            'description' => 'Custom server',
            'owner_id' => $user->id,
            'egg_id' => $egg->id,
            'nest_id' => $egg->nest_id,
            'memory' => $limits['memory'],
            'swap' => 0,
            'disk' => $limits['disk'],
            'io' => 500,
            'cpu' => $limits['cpu'],
            'threads' => null,
            'oom_disabled' => true,
            'database_limit' => $limits['database_limit'],
            'allocation_limit' => $limits['allocation_limit'],
            'backup_limit' => $limits['backup_limit'],
            'startup' => $egg->startup,
            'image' => (string) Arr::first($egg->docker_images ?? []),
            'environment' => $environment,
            'skip_scripts' => false,
            'start_on_completion' => true,
        ];

        $deployment = (new DeploymentObject())->setLocations($locationIds)->setDedicated(false)->setPorts([]);

        $server = $this->creation->handle($data, $deployment);
        $this->addTxAdminPort($server, $egg);

        return $server;
    }

    /**
     * A FiveM server needs a second port for txAdmin. When the egg is FiveM, a free port on the same node is given to the
     * server on top of the game port, without counting against the extra ports the client paid for. If none is free, the
     * server is still made (the port can be added by hand later).
     */
    private function addTxAdminPort(Server $server, Egg $egg): void
    {
        if (stripos((string) $egg->name, 'fivem') === false) {
            return;
        }

        try {
            $extra = Allocation::query()
                ->where('node_id', $server->node_id)
                ->whereNull('server_id')
                ->where('id', '!=', $server->allocation_id)
                ->first();

            if (!$extra) {
                Log::warning('No free port for txAdmin on the node of a new FiveM server.', ['server' => $server->id]);

                return;
            }

            $extra->update(['server_id' => $server->id]);
            // Keep it on top of the ports the client bought, so their own limit stays free.
            Server::query()->whereKey($server->id)->update(['allocation_limit' => (int) $server->allocation_limit + 1]);
        } catch (\Throwable $exception) {
            Log::warning('The txAdmin port could not be assigned.', ['server' => $server->id, 'error' => $exception->getMessage()]);
        }
    }
}
