<?php

namespace Pterodactyl\Services\Web;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Objects\DeploymentObject;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\User;
use Pterodactyl\Models\WebPlan;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;
use Pterodactyl\Services\Servers\ServerCreationService;

/**
 * Makes the server that runs a site, from the plan, and changes the image of one that exists (the version of PHP). The
 * node and the port are chosen by the panel among the nodes of the location of the plan that have room.
 */
class WebProvisioner
{
    public function __construct(private ServerCreationService $creation, private DaemonServerRepository $daemon)
    {
    }

    /**
     * @throws \Throwable when the server cannot be made (no room, no free port, the node does not answer...)
     */
    public function provision(User $owner, WebPlan $plan, string $name, ?string $php): Server
    {
        $egg = Egg::query()->with('variables')->findOrFail($plan->egg_id);

        $environment = [];
        foreach ($egg->variables as $variable) {
            $environment[$variable->env_variable] = (string) $variable->default_value;
        }
        foreach ($plan->environment ?? [] as $variable => $value) {
            if (array_key_exists($variable, $environment)) {
                $environment[$variable] = (string) $value;
            }
        }

        $data = [
            'name' => Str::limit(preg_replace('/[^\w.\- ]+/u', '', $name) ?: 'Site', 60, ''),
            'description' => 'Web hosting',
            'owner_id' => $owner->id,
            'egg_id' => $egg->id,
            'nest_id' => $egg->nest_id,
            'memory' => $plan->memory,
            'swap' => 0,
            'disk' => $plan->disk,
            'io' => 500,
            'cpu' => $plan->cpu,
            'threads' => null,
            'oom_disabled' => true,
            'database_limit' => $plan->database_limit,
            'allocation_limit' => 0,
            'backup_limit' => $plan->backup_limit,
            'startup' => $egg->startup,
            'image' => $plan->imageFor($php) ?? (string) Arr::first($egg->docker_images ?? []),
            'environment' => $environment,
            'skip_scripts' => false,
            'start_on_completion' => true,
        ];

        $deployment = (new DeploymentObject())->setLocations([$plan->location_id])->setDedicated(false)->setPorts([]);

        return $this->creation->handle($data, $deployment);
    }

    /**
     * Puts another image on a server (another version of PHP). It applies at the next start.
     *
     * @throws DisplayException
     */
    public function applyImage(Server $server, string $image): void
    {
        $previous = $server->image;
        $server->update(['image' => $image]);

        try {
            $this->daemon->setServer($server)->sync();
        } catch (\Throwable $exception) {
            $server->update(['image' => $previous]);

            throw new DisplayException('The server could not be reached, the version of PHP was not changed.');
        }
    }
}
