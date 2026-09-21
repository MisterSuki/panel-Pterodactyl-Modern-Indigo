<?php

namespace Pterodactyl\Services\Shop;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
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

        return $this->creation->handle($data, $deployment);
    }
}
