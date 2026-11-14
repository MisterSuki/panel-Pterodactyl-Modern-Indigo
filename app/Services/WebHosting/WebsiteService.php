<?php

namespace Pterodactyl\Services\WebHosting;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerVariable;
use Pterodactyl\Models\User;
use Pterodactyl\Services\Servers\ReinstallServerService;
use Pterodactyl\Services\Servers\ServerCreationService;
use Pterodactyl\Services\Servers\StartupModificationService;

/**
 * A website made in a few fields (name, owner, resources: the node and the port are found alone), and its kind
 * (PHP / HTML, Node.js, Python) chosen by its owner in the panel and changed whenever they want: the kinds are the
 * eggs of the web hosting nest, but nobody has to pick an egg.
 */
class WebsiteService
{
    public function __construct(
        private ServerCreationService $creation,
        private StartupModificationService $startup,
        private ReinstallServerService $reinstall,
        private WebHostingService $web,
    ) {
    }

    /**
     * The kinds of site: the eggs of the web hosting nest, PHP first.
     *
     * @return Collection<int, Egg>
     */
    public function kinds(): Collection
    {
        return Egg::query()->with('variables')->whereHas('nest', fn ($q) => $q->where('name', WebHostingSetup::NEST))->get()
            ->filter(fn (Egg $egg) => in_array(WebHostingService::FEATURE, (array) $egg->inherit_features, true))
            ->sortBy(fn (Egg $egg) => ['php' => 0, 'nodejs' => 1, 'python' => 2, 'emby' => 3][$this->key($egg)] ?? 4)
            ->values();
    }

    /**
     * What the site page shows: the kind of the site, the other kinds, and the versions of each.
     */
    public function present(Server $server): array
    {
        return [
            'current' => $server->egg_id,
            'image' => $server->image,
            'kinds' => $this->kinds()->map(fn (Egg $egg) => [
                'id' => $egg->id,
                'name' => $egg->name,
                'key' => $this->key($egg),
                'versions' => collect($egg->docker_images ?? [])->map(fn ($image, $label) => ['label' => (string) $label, 'image' => (string) $image])->values()->all(),
            ])->all(),
        ];
    }

    /**
     * Makes a site for a user: the kind is PHP / HTML until they choose another; the node is the given one or the
     * first one with a free port (nodes running the web agent first).
     *
     * @throws DisplayException
     */
    public function create(User $owner, string $name, int $memory, int $disk, int $cpu, ?int $nodeId = null): Server
    {
        /** @var Egg|null $egg */
        $egg = $this->kinds()->first();
        if (!$egg) {
            throw new DisplayException('The web hosting is not set up yet: install its eggs first (button above).');
        }

        $allocation = $this->freeAllocation($nodeId);
        if (!$allocation) {
            throw new DisplayException($nodeId ? 'This node has no free port left: add ports (allocations) to it.' : 'No node has a free port left: add ports (allocations) to a node.');
        }

        $environment = [];
        foreach ($egg->variables as $variable) {
            $environment[$variable->env_variable] = (string) $variable->default_value;
        }

        return $this->creation->handle([
            'name' => Str::limit(trim($name) ?: 'Site', 60, ''),
            'description' => 'Website',
            'owner_id' => $owner->id,
            'egg_id' => $egg->id,
            'nest_id' => $egg->nest_id,
            'node_id' => $allocation->node_id,
            'allocation_id' => $allocation->id,
            'memory' => $memory,
            'swap' => 0,
            'disk' => $disk,
            'io' => 500,
            'cpu' => $cpu,
            'threads' => null,
            'oom_disabled' => true,
            'database_limit' => 1,
            'allocation_limit' => 0,
            'backup_limit' => 2,
            'startup' => $egg->startup,
            'image' => (string) Arr::first($egg->docker_images ?? []),
            'environment' => $environment,
            'skip_scripts' => false,
            'start_on_completion' => true,
        ]);
    }

    /**
     * Changes the kind of a site (and its version): the files are kept; the install of the new kind adds its example
     * files only where nothing is there yet.
     *
     * @throws DisplayException
     */
    public function switchKind(Server $server, int $eggId, ?string $image): Server
    {
        if (!WebHostingService::isWebsite($server)) {
            throw new DisplayException('This server is not a website.');
        }
        /** @var Egg|null $egg */
        $egg = $this->kinds()->firstWhere('id', $eggId);
        if (!$egg) {
            throw new DisplayException('This kind of site does not exist.');
        }
        $images = array_values(array_map('strval', (array) ($egg->docker_images ?? [])));
        if ($image !== null && !in_array($image, $images, true)) {
            throw new DisplayException('This version is not offered for this kind of site.');
        }
        $image ??= $images[0] ?? $server->image;
        $changesKind = $server->egg_id !== $egg->id;

        $environment = [];
        foreach ($egg->variables as $variable) {
            $kept = ServerVariable::query()->where('server_id', $server->id)->where('variable_id', $variable->id)->value('variable_value');
            $environment[$variable->env_variable] = (string) ($kept ?? $variable->default_value);
        }

        $server = $this->startup->setUserLevel(User::USER_LEVEL_ADMIN)->handle($server, [
            'egg_id' => $egg->id,
            'docker_image' => $image,
            'startup' => $changesKind ? $egg->startup : $server->startup,
            'environment' => $environment,
            'skip_scripts' => false,
        ]);

        // The variables of the former kind mean nothing to the new one.
        if ($changesKind) {
            ServerVariable::query()->where('server_id', $server->id)
                ->whereNotIn('variable_id', $egg->variables->pluck('id'))->delete();
            if ($server->isInstalled()) {
                $this->reinstall->handle($server);
            }
        }

        return $server->fresh();
    }

    private function freeAllocation(?int $nodeId): ?Allocation
    {
        $nodes = $nodeId ? Node::query()->whereKey($nodeId)->get() : Node::query()->orderBy('id')->get();
        $nodes = $nodes->sortBy(fn (Node $node) => $this->web->agentOf($node) ? 0 : 1);
        foreach ($nodes as $node) {
            /** @var Allocation|null $allocation */
            $allocation = Allocation::query()->where('node_id', $node->id)->whereNull('server_id')->orderBy('port')->first();
            if ($allocation) {
                return $allocation;
            }
        }

        return null;
    }

    private function key(Egg $egg): string
    {
        $name = strtolower($egg->name);

        return match (true) {
            str_contains($name, 'emby') => 'emby',
            str_contains($name, 'node') => 'nodejs',
            str_contains($name, 'python') => 'python',
            str_contains($name, 'php') => 'php',
            default => 'other',
        };
    }
}
