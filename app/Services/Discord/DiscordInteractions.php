<?php

namespace Pterodactyl\Services\Discord;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Builder;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\Permission;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerCustomDomain;
use Pterodactyl\Models\ServerSubdomain;
use Pterodactyl\Models\User;
use Pterodactyl\Repositories\Wings\DaemonPowerRepository;
use Pterodactyl\Repositories\Wings\DaemonServerListRepository;
use Pterodactyl\Services\Servers\ServerWatcher;

/**
 * What the bot answers. The person is found by their Discord account, linked to the panel from its Account page;
 * a server is only shown or driven with the rights the person has on it in the panel.
 */
class DiscordInteractions
{
    private const PING = 1;

    private const COMMAND = 2;

    private const BUTTON = 3;

    private const AUTOCOMPLETE = 4;

    private const TEXTS = [
        'not_linked' => ['Your Discord account is not linked', 'Link it on the Account page of the panel, then try again.', 'Ton compte Discord n\'est pas lié', 'Lie-le sur la page Compte du panel, puis réessaie.'],
        'not_found' => ['Server not found', 'Choose one of your servers in the list.', 'Serveur introuvable', 'Choisis un de tes serveurs dans la liste.'],
        'no_right' => ['Not allowed', 'You do not have the right to do this on {name}.', 'Action non autorisée', 'Tu n\'as pas le droit de faire ça sur {name}.'],
        'unavailable' => ['{name} is unavailable', 'It is suspended, installing or being moved.', '{name} est indisponible', 'Il est suspendu, en installation ou en transfert.'],
        'node_down' => ['The node does not answer', 'The machine of {name} cannot be reached for now.', 'Le node ne répond pas', 'La machine de {name} est injoignable pour le moment.'],
        'sent_start' => ['{name} is starting', 'Asked by {user} from Discord.', '{name} démarre', 'Demandé par {user} depuis Discord.'],
        'sent_restart' => ['{name} is restarting', 'Asked by {user} from Discord.', '{name} redémarre', 'Demandé par {user} depuis Discord.'],
        'sent_stop' => ['{name} is stopping', 'Asked by {user} from Discord.', '{name} s\'arrête', 'Demandé par {user} depuis Discord.'],
        'servers' => ['Your servers', 'You have no server yet.', 'Tes serveurs', 'Tu n\'as pas encore de serveur.'],
    ];

    private const WORDS = [
        'running' => ['Running', 'En marche'], 'starting' => ['Starting', 'Démarrage'], 'stopping' => ['Stopping', 'Arrêt'],
        'offline' => ['Offline', 'Hors ligne'], 'suspended' => ['Suspended', 'Suspendu'], 'unknown' => ['Unknown', 'Inconnu'],
        'start' => ['Start', 'Démarrer'], 'restart' => ['Restart', 'Redémarrer'], 'stop' => ['Stop', 'Arrêter'],
        'refresh' => ['Refresh', 'Actualiser'], 'open' => ['Open in the panel', 'Ouvrir dans le panel'],
        'address' => ['Address', 'Adresse'], 'uptime' => ['Online for', 'En ligne depuis'], 'memory' => ['Memory', 'Mémoire'],
        'disk' => ['Disk', 'Disque'], 'link' => ['Link my account', 'Lier mon compte'],
    ];

    private const ICONS = ['running' => '🟢', 'starting' => '🟡', 'stopping' => '🟡', 'offline' => '🔴', 'suspended' => '🟣', 'unknown' => '⚪'];

    private bool $french = false;

    public function __construct(
        private CacheRepository $cache,
        private DaemonServerListRepository $list,
        private DaemonPowerRepository $power,
    ) {
    }

    public function handle(array $interaction): array
    {
        $type = (int) ($interaction['type'] ?? 0);
        if ($type === self::PING) {
            return ['type' => 1];
        }
        $this->french = str_starts_with((string) ($interaction['locale'] ?? ''), 'fr');
        $discordId = (string) ($interaction['member']['user']['id'] ?? $interaction['user']['id'] ?? '');
        $user = $discordId !== '' ? User::query()->where('discord_id', $discordId)->first() : null;

        if ($type === self::AUTOCOMPLETE) {
            return ['type' => 8, 'data' => ['choices' => $user ? $this->choices($user, $this->focused($interaction)) : []]];
        }
        if ($user === null) {
            $link = DiscordBot::link($this->word('link'), rtrim((string) config('app.url'), '/') . '/account');

            return $this->reply($this->card('not_linked', [], 'warning', array_filter([$link])));
        }

        if ($type === self::BUTTON) {
            // p:<signal>:<server> for a power button, s:<server> to refresh the card.
            $parts = explode(':', (string) ($interaction['data']['custom_id'] ?? ''));
            $server = $this->server($user, (string) end($parts));
            if ($server === null) {
                return $this->update($this->card('not_found', [], 'warning'));
            }
            if ($parts[0] === 'p' && in_array($parts[1] ?? '', ['start', 'restart', 'stop'], true)) {
                $refused = $this->power($user, $server, $parts[1]);
                if ($refused !== null) {
                    return $this->reply($refused);
                }
            }

            return $this->update($this->status($server, $user));
        }

        $name = (string) ($interaction['data']['name'] ?? '');
        if ($name === 'servers') {
            return $this->reply($this->servers($user));
        }
        $server = $this->server($user, (string) $this->option($interaction, 'server'));
        if ($server === null) {
            return $this->reply($this->card('not_found', [], 'warning'));
        }
        if (in_array($name, ['start', 'restart', 'stop'], true)) {
            $refused = $this->power($user, $server, $name);
            if ($refused !== null) {
                return $this->reply($refused);
            }

            return $this->reply($this->card('sent_' . $name, [], $name === 'stop' ? 'info' : 'success', [], ['name' => $server->name, 'user' => $user->username]));
        }

        return $this->reply($this->status($server, $user));
    }

    /**
     * Sends the power action. Returns a card that says why it was refused, or null when it was sent.
     */
    private function power(User $user, Server $server, string $signal): ?array
    {
        $permission = ['start' => Permission::ACTION_CONTROL_START, 'restart' => Permission::ACTION_CONTROL_RESTART, 'stop' => Permission::ACTION_CONTROL_STOP][$signal];
        if (!$user->can($permission, $server)) {
            return $this->card('no_right', [], 'warning', [], ['name' => $server->name]);
        }
        if ($server->status !== null) {
            return $this->card('unavailable', [], 'warning', [], ['name' => $server->name]);
        }
        try {
            $this->power->setServer($server)->send($signal);
        } catch (\Throwable) {
            return $this->card('node_down', [], 'danger', [], ['name' => $server->name]);
        }
        Activity::event('server:power.' . $signal)->actor($user)->subject($server)->property('source', 'discord')->log();
        ServerWatcher::powerAction($server, $signal, $user->username . ' (Discord)');
        $this->cache->forget('discord-states:' . $server->node_id);

        return null;
    }

    private function servers(User $user): array
    {
        $servers = $user->accessibleServers()->with('node')->orderBy('servers.name')->limit(25)->get();
        $lines = [];
        foreach ($servers->groupBy('node_id') as $group) {
            $states = $this->states($group->first()->node);
            foreach ($group as $server) {
                $state = $this->state($server, $states);
                $lines[] = (self::ICONS[$state] ?? '⚪') . ' **' . $this->clean($server->name) . '** · ' . $this->word($state);
            }
        }
        [$title, $empty] = $this->text('servers');

        return DiscordBot::card($title, $lines ?: [$empty], 'info');
    }

    private function status(Server $server, User $user): array
    {
        $server->loadMissing(['node', 'allocation']);
        $states = $this->states($server->node);
        $state = $this->state($server, $states);
        $live = $states[$server->uuid] ?? null;

        $lines = [(self::ICONS[$state] ?? '⚪') . ' ' . $this->word($state)];
        $lines[] = '**' . $this->word('address') . '** · `' . $this->address($server) . '`';
        if ($live !== null && $state === 'running') {
            $memory = $this->size((int) $live['memory']) . ($server->memory > 0 ? ' / ' . $this->size($server->memory * 1048576) : '');
            $lines[] = '**CPU** · ' . round((float) ($live['cpu'] ?? 0), 1) . ' %   **' . $this->word('memory') . '** · ' . $memory;
            $lines[] = '**' . $this->word('uptime') . '** · ' . $this->uptime((int) $live['uptime']);
        }
        if ($live !== null) {
            $lines[] = '**' . $this->word('disk') . '** · ' . $this->size((int) $live['disk']);
        }
        $players = $this->cache->get('players:' . $server->uuid);
        if (is_array($players) && is_array($players['value'] ?? null) && $state === 'running') {
            $count = $players['value'];
            $lines[] = '👥 ' . $count['players'] . ($count['max_players'] ? ' / ' . $count['max_players'] : '');
        }

        $off = $state === 'offline';
        $usable = $server->status === null;
        $buttons = [
            DiscordBot::button($this->word('start'), 'p:start:' . $server->uuidShort, 3, !$usable || !$off || !$user->can(Permission::ACTION_CONTROL_START, $server)),
            DiscordBot::button($this->word('restart'), 'p:restart:' . $server->uuidShort, 1, !$usable || $off || !$user->can(Permission::ACTION_CONTROL_RESTART, $server)),
            DiscordBot::button($this->word('stop'), 'p:stop:' . $server->uuidShort, 4, !$usable || $off || !$user->can(Permission::ACTION_CONTROL_STOP, $server)),
            DiscordBot::button($this->word('refresh'), 's:' . $server->uuidShort),
        ];
        $open = DiscordBot::link($this->word('open'), rtrim((string) config('app.url'), '/') . '/server/' . $server->uuidShort);
        if ($open !== null) {
            $buttons[] = $open;
        }

        $kind = ['running' => 'success', 'offline' => 'danger', 'suspended' => 'warning'][$state] ?? 'info';

        return DiscordBot::card($this->clean($server->name), $lines, $kind, $buttons);
    }

    /**
     * @return array<int, array{name: string, value: string}>
     */
    private function choices(User $user, string $typed): array
    {
        $typed = mb_strtolower(trim($typed));

        return $user->accessibleServers()
            ->when($typed !== '', fn (Builder $q) => $q->whereRaw('LOWER(servers.name) LIKE ?', ['%' . addcslashes($typed, '%_\\') . '%']))
            ->orderBy('servers.name')->limit(25)->get()
            ->map(fn (Server $s) => ['name' => mb_substr($s->name, 0, 100), 'value' => $s->uuidShort])
            ->values()->all();
    }

    /**
     * The server chosen in the list (its short id), or typed by its name.
     */
    private function server(User $user, string $value): ?Server
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        return $user->accessibleServers()
            ->where(fn (Builder $q) => $q->where('servers.uuidShort', $value)->orWhereRaw('LOWER(servers.name) = ?', [mb_strtolower($value)]))
            ->first();
    }

    /**
     * The state of every server of a node, in one request kept a few seconds; null when the node does not answer.
     */
    private function states(Node $node): ?array
    {
        $states = $this->cache->remember('discord-states:' . $node->id, 5, function () use ($node) {
            try {
                return ['ok' => true, 'states' => $this->list->setNode($node)->all()];
            } catch (\Throwable) {
                return ['ok' => false, 'states' => []];
            }
        });

        return $states['ok'] ? $states['states'] : null;
    }

    private function state(Server $server, ?array $states): string
    {
        if ($server->isSuspended()) {
            return 'suspended';
        }
        $state = $states[$server->uuid]['state'] ?? 'unknown';

        return isset(self::ICONS[$state]) ? $state : 'unknown';
    }

    private function address(Server $server): string
    {
        $domain = ServerCustomDomain::query()->where('server_id', $server->id)->where('allocation_id', $server->allocation_id)
            ->whereNotNull('verified_at')->value('hostname');
        if (!$domain) {
            $sub = ServerSubdomain::query()->with('domain')->where('server_id', $server->id)->where('allocation_id', $server->allocation_id)->first();
            $domain = $sub?->fqdn();
        }

        return $domain ?: ($server->allocation ? $server->allocation->alias . ':' . $server->allocation->port : '?');
    }

    private function focused(array $interaction): string
    {
        foreach ((array) ($interaction['data']['options'] ?? []) as $option) {
            if (!empty($option['focused'])) {
                return (string) ($option['value'] ?? '');
            }
        }

        return '';
    }

    private function option(array $interaction, string $name): ?string
    {
        foreach ((array) ($interaction['data']['options'] ?? []) as $option) {
            if (($option['name'] ?? '') === $name) {
                return (string) ($option['value'] ?? '');
            }
        }

        return null;
    }

    private function card(string $key, array $lines, string $kind, array $buttons = [], array $vars = []): array
    {
        [$title, $body] = $this->text($key, $vars);

        return DiscordBot::card($title, array_merge([$body], $lines), $kind, $buttons);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function text(string $key, array $vars = []): array
    {
        $set = self::TEXTS[$key];
        $replace = [];
        foreach ($vars as $name => $value) {
            $replace['{' . $name . '}'] = $this->clean((string) $value);
        }

        return $this->french ? [strtr($set[2], $replace), strtr($set[3], $replace)] : [strtr($set[0], $replace), strtr($set[1], $replace)];
    }

    private function word(string $key): string
    {
        return self::WORDS[$key][$this->french ? 1 : 0] ?? $key;
    }

    /**
     * A name written by a user can hold Discord formatting: it is shown as it is.
     */
    private function clean(string $text): string
    {
        return mb_substr(preg_replace('/([\\\\*_~`|>#\[\]()-])/u', '\\\\$1', $text) ?? $text, 0, 100);
    }

    private function reply(array $card): array
    {
        return ['type' => 4, 'data' => ['flags' => DiscordBot::FLAG_COMPONENTS | DiscordBot::FLAG_EPHEMERAL, 'components' => [$card], 'allowed_mentions' => ['parse' => []]]];
    }

    private function update(array $card): array
    {
        return ['type' => 7, 'data' => ['flags' => DiscordBot::FLAG_COMPONENTS, 'components' => [$card], 'allowed_mentions' => ['parse' => []]]];
    }

    private function size(int $bytes): string
    {
        return $bytes >= 1073741824 ? round($bytes / 1073741824, 1) . ' GiB' : round($bytes / 1048576) . ' MiB';
    }

    private function uptime(int $ms): string
    {
        $minutes = intdiv($ms, 60000);
        $hours = intdiv($minutes, 60);
        if ($minutes < 60) {
            return max(1, $minutes) . ' min';
        }

        return $hours < 48 ? $hours . ' h ' . ($minutes % 60) . ' min' : intdiv($hours, 24) . ($this->french ? ' j ' : ' d ') . ($hours % 24) . ' h';
    }
}
