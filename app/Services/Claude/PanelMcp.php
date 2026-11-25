<?php

namespace Pterodactyl\Services\Claude;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Models\AssistantFileChange;
use Pterodactyl\Models\ClaudeConnection;
use Pterodactyl\Models\Permission;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\User;
use Pterodactyl\Repositories\Wings\DaemonCommandRepository;
use Pterodactyl\Repositories\Wings\DaemonConsoleRepository;
use Pterodactyl\Repositories\Wings\DaemonPowerRepository;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;
use Pterodactyl\Services\Assistant\AssistantFiles;
use Pterodactyl\Services\Assistant\ServerAssistant;

/**
 * The panel as a connector of the Claude app (Model Context Protocol, JSON-RPC over HTTP): Claude lists the servers
 * of the person, reads their console, sends commands, starts or stops them, and reads or changes their files, always
 * with the rights of the person in the panel. A read-only link cannot change anything. A change of a file is kept
 * in the history of the AI assistant of the server, so it can be put back from the panel at any time.
 */
class PanelMcp
{
    public const PROTOCOLS = ['2025-06-18', '2025-03-26', '2024-11-05'];

    // The tools that change something (refused with a read-only link).
    private const WRITING = ['send_command', 'power', 'edit_file', 'create_file', 'undo_file_change'];

    public function __construct(
        private DaemonServerRepository $daemon,
        private DaemonConsoleRepository $console,
        private DaemonCommandRepository $commands,
        private DaemonPowerRepository $power,
        private AssistantFiles $files,
        private ServerAssistant $assistant,
    ) {
    }

    /**
     * Answers one JSON-RPC message (null for a notification, which gets no answer).
     */
    public function handle(array $message, ClaudeConnection $connection): ?array
    {
        $id = $message['id'] ?? null;
        $method = (string) ($message['method'] ?? '');
        $params = (array) ($message['params'] ?? []);
        if (!array_key_exists('id', $message)) {
            return null;
        }

        try {
            $result = match ($method) {
                'initialize' => $this->initialize($params, $connection),
                'ping' => new \stdClass(),
                'tools/list' => ['tools' => $this->tools($connection)],
                'tools/call' => $this->call($connection, (string) ($params['name'] ?? ''), (array) ($params['arguments'] ?? [])),
                'resources/list' => ['resources' => []],
                'prompts/list' => ['prompts' => []],
                default => throw new \BadMethodCallException($method),
            };

            return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
        } catch (\BadMethodCallException) {
            return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => -32601, 'message' => "Method not found: $method"]];
        }
    }

    private function initialize(array $params, ClaudeConnection $connection): array
    {
        $asked = (string) ($params['protocolVersion'] ?? '');
        $user = $connection->user;

        return [
            'protocolVersion' => in_array($asked, self::PROTOCOLS, true) ? $asked : self::PROTOCOLS[0],
            'capabilities' => ['tools' => ['listChanged' => false]],
            'serverInfo' => ['name' => Str::slug((string) config('app.name', 'pterodactyl')) . '-panel', 'title' => config('app.name', 'Pterodactyl') . ' panel', 'version' => (string) config('app.version')],
            'instructions' => implode("\n", [
                'This connector drives the game servers of ' . $user->username . ' on a Pterodactyl hosting panel' . ($connection->read_only ? ' (read only: nothing can be changed).' : '.'),
                'Start with list_servers; the other tools take the identifier (8 characters) or the name of a server.',
                'To fix a problem: read_console, then list_files / read_file, then edit_file with exact search/replace pieces copied from the file. Every change is kept and can be undone with undo_file_change (or from the panel).',
                'Values shown as (hidden) are secrets: never write them. Restart the server after changing its configuration if needed.',
            ]),
        ];
    }

    private function tools(ClaudeConnection $connection): array
    {
        $server = ['type' => 'string', 'description' => 'Identifier (8 characters, from list_servers) or exact name of the server.'];
        $tool = fn (string $name, string $title, string $description, array $properties, array $required, bool $readOnly, bool $destructive = false) => [
            'name' => $name,
            'title' => $title,
            'description' => $description,
            'inputSchema' => ['type' => 'object', 'properties' => (object) $properties, 'required' => $required],
            'annotations' => ['title' => $title, 'readOnlyHint' => $readOnly, 'destructiveHint' => $destructive, 'openWorldHint' => false],
        ];

        $tools = [
            $tool('list_servers', 'List my servers', 'Lists the servers of the account: name, identifier, game, state, CPU and memory.', [], [], true),
            $tool('server_status', 'Server status', 'State, CPU, memory, disk, uptime and limits of a server.', ['server' => $server], ['server'], true),
            $tool('read_console', 'Read the console', 'The last lines of the console of a server (lines repeated in a row are grouped).', ['server' => $server, 'lines' => ['type' => 'integer', 'description' => 'How many lines (default 120, at most 400).']], ['server'], true),
            $tool('list_files', 'List files', 'Files and folders of a folder of a server.', ['server' => $server, 'path' => ['type' => 'string', 'description' => 'Folder, like "/" or "/resources".']], ['server'], true),
            $tool('read_file', 'Read a file', 'Reads a text file of a server. Secret values are shown as (hidden).', ['server' => $server, 'path' => ['type' => 'string']], ['server', 'path'], true),
            $tool('file_changes', 'File changes', 'The last changes of files of a server made by an AI (here or in the panel), with their id to undo one.', ['server' => $server], ['server'], true),
        ];
        if (!$connection->read_only) {
            $tools = array_merge($tools, [
                $tool('send_command', 'Send a command', 'Sends a command to the console of a running server (like "say hello" or "refresh").', ['server' => $server, 'command' => ['type' => 'string']], ['server', 'command'], false),
                $tool('power', 'Start / stop / restart', 'Starts, stops, restarts or kills (forces to stop) a server.', ['server' => $server, 'action' => ['type' => 'string', 'enum' => ['start', 'stop', 'restart', 'kill']]], ['server', 'action'], false, true),
                $tool('edit_file', 'Edit a file', 'Changes an existing text file by replacing exact pieces of it ("search" copied exactly from the file, found once). The change is kept and can be undone.', [
                    'server' => $server,
                    'path' => ['type' => 'string'],
                    'summary' => ['type' => 'string', 'description' => 'What the change does, short.'],
                    'edits' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['search' => ['type' => 'string'], 'replace' => ['type' => 'string']], 'required' => ['search', 'replace']]],
                ], ['server', 'path', 'edits'], false),
                $tool('create_file', 'Create a file', 'Creates a new text file (it must not exist). Can be undone.', ['server' => $server, 'path' => ['type' => 'string'], 'content' => ['type' => 'string'], 'summary' => ['type' => 'string']], ['server', 'path', 'content'], false),
                $tool('undo_file_change', 'Undo a file change', 'Puts a file back as it was before a change (id from file_changes).', ['server' => $server, 'change_id' => ['type' => 'integer'], 'force' => ['type' => 'boolean', 'description' => 'Even if the file was changed again since.']], ['server', 'change_id'], false, true),
            ]);
        }

        return $tools;
    }

    /**
     * Runs a tool; an error is given back to Claude as a text it can read (isError).
     */
    private function call(ClaudeConnection $connection, string $name, array $arguments): array
    {
        $user = $connection->user;
        try {
            if (in_array($name, self::WRITING, true) && $connection->read_only) {
                throw new DisplayException('This link of Claude is read only: create a link with full access in the panel (Account > Claude) to change things.');
            }
            $text = match ($name) {
                'list_servers' => $this->listServers($user),
                'server_status' => $this->status($user, $this->server($user, $arguments, Permission::ACTION_WEBSOCKET_CONNECT)),
                'read_console' => $this->readConsole($this->server($user, $arguments, Permission::ACTION_WEBSOCKET_CONNECT), (int) ($arguments['lines'] ?? 120)),
                'list_files', 'read_file' => $this->fileTool($user, $arguments, $name === 'list_files' ? 'list_directory' : 'read_file'),
                'file_changes' => $this->changes($this->server($user, $arguments, Permission::ACTION_FILE_READ)),
                'send_command' => $this->sendCommand($user, $this->server($user, $arguments, Permission::ACTION_CONTROL_CONSOLE), (string) ($arguments['command'] ?? '')),
                'power' => $this->powerAction($user, $arguments),
                'edit_file', 'create_file' => $this->change($user, $arguments, $name),
                'undo_file_change' => $this->undo($user, $arguments),
                default => throw new DisplayException("Unknown tool: $name"),
            };

            return ['content' => [['type' => 'text', 'text' => $text]], 'isError' => false];
        } catch (DisplayException $exception) {
            return ['content' => [['type' => 'text', 'text' => 'Error: ' . $exception->getMessage()]], 'isError' => true];
        } catch (\Throwable $exception) {
            report($exception);

            return ['content' => [['type' => 'text', 'text' => 'Error: the server did not answer (the node may be offline, or the server is installing or suspended).']], 'isError' => true];
        }
    }

    /**
     * The servers the person owns or was invited to.
     *
     * @return Collection<int, Server>
     */
    private function servers(User $user): Collection
    {
        return Server::query()->with(['egg', 'node'])
            ->where(fn ($q) => $q->where('owner_id', $user->id)->orWhereHas('subusers', fn ($s) => $s->where('user_id', $user->id)))
            ->orderBy('name')->limit(50)->get();
    }

    /**
     * The server named in the arguments, if the person may do $permission on it.
     *
     * @throws DisplayException
     */
    private function server(User $user, array $arguments, string $permission): Server
    {
        $wanted = trim((string) ($arguments['server'] ?? ''));
        if ($wanted === '') {
            throw new DisplayException('Give the server (its identifier or its name, see list_servers).');
        }
        /** @var Server|null $server */
        $server = Server::query()->where('uuidShort', $wanted)->orWhere('uuid', $wanted)->first()
            ?? $this->servers($user)->first(fn (Server $s) => mb_strtolower($s->name) === mb_strtolower($wanted));
        if (!$server || !$user->can(Permission::ACTION_WEBSOCKET_CONNECT, $server)) {
            throw new DisplayException("No server \"$wanted\" in this account (see list_servers).");
        }
        if (!$user->can($permission, $server)) {
            throw new DisplayException("This account does not have the right \"$permission\" on this server.");
        }

        return $server;
    }

    private function listServers(User $user): string
    {
        $servers = $this->servers($user);
        if ($servers->isEmpty()) {
            return 'This account has no server.';
        }

        return $servers->map(function (Server $server) {
            $live = $this->live($server);

            return sprintf(
                '- %s (identifier %s): %s, %s%s',
                $server->name,
                $server->uuidShort,
                $server->egg->name ?? 'unknown game',
                $live['state'] ?? 'state unknown',
                isset($live['cpu']) ? sprintf(', CPU %.1f%%, memory %s', $live['cpu'], $this->bytes($live['memory'])) : ''
            );
        })->implode("\n");
    }

    private function status(User $user, Server $server): string
    {
        $live = $this->live($server);

        return implode("\n", array_filter([
            "Server: {$server->name} ({$server->uuidShort}), " . ($server->egg->name ?? 'unknown game') . ', node ' . ($server->node->name ?? '?'),
            'State: ' . ($live['state'] ?? 'unknown (the node did not answer)'),
            isset($live['cpu']) ? sprintf('CPU: %.1f%% (limit %s)', $live['cpu'], $server->cpu ? $server->cpu . '%' : 'none') : null,
            isset($live['memory']) ? 'Memory: ' . $this->bytes($live['memory']) . ' / ' . ($server->memory ? $server->memory . ' MiB' : 'no limit') : null,
            isset($live['disk']) ? 'Disk: ' . $this->bytes($live['disk']) . ' / ' . ($server->disk ? $server->disk . ' MiB' : 'no limit') : null,
            isset($live['uptime']) && $live['uptime'] > 0 ? 'Uptime: ' . gmdate('H\h i\m', intdiv((int) $live['uptime'], 1000)) : null,
            $server->status ? "Panel status: {$server->status}" : null,
        ]));
    }

    private function readConsole(Server $server, int $lines): string
    {
        $this->console->setServer($server);
        $raw = $this->console->lines(max(10, min(400, $lines)));
        $text = $this->assistant->cleanConsole(implode("\n", $raw));

        return $text === '' ? '(the console is empty)' : $text;
    }

    private function fileTool(User $user, array $arguments, string $tool): string
    {
        $server = $this->server($user, $arguments, Permission::ACTION_FILE_READ);
        $proposed = [];

        return $this->files->run($server, $user, $tool, ['path' => (string) ($arguments['path'] ?? '/')], $proposed);
    }

    /**
     * Changes or creates a file: prepared like a change of the AI assistant, then applied at once (Claude already
     * asked the person). It stays in the history, so it can be undone.
     *
     * @throws DisplayException
     */
    private function change(User $user, array $arguments, string $name): string
    {
        $server = $this->server($user, $arguments, $name === 'edit_file' ? Permission::ACTION_FILE_UPDATE : Permission::ACTION_FILE_CREATE);
        $proposed = [];
        $arguments['summary'] = trim((string) ($arguments['summary'] ?? '')) ?: 'Changed from Claude';
        $result = $this->files->run($server, $user, $name === 'edit_file' ? 'propose_change' : 'create_file', $arguments, $proposed);
        if (!$proposed) {
            return $result;
        }
        /** @var AssistantFileChange $change */
        $change = $proposed[0];
        $this->files->apply($server, $user, $change);

        return "Done: {$change->path} " . ($change->before === null ? 'was created' : 'was changed') . " (change id {$change->id}). It can be undone with undo_file_change, or in the panel (AI assistant > history). Restart the server if it only reads this file when it starts.";
    }

    private function changes(Server $server): string
    {
        $list = $this->files->history($server)->take(20);
        if ($list->isEmpty()) {
            return 'No change of file on this server.';
        }

        return $list->map(fn (AssistantFileChange $c) => sprintf('- id %d: %s, %s, %s (%s)', $c->id, $c->path, $c->summary ?: '-', $c->state, $c->created_at?->toDateTimeString()))->implode("\n");
    }

    private function undo(User $user, array $arguments): string
    {
        $server = $this->server($user, $arguments, Permission::ACTION_FILE_UPDATE);
        /** @var AssistantFileChange|null $change */
        $change = AssistantFileChange::query()->where('server_id', $server->id)->find((int) ($arguments['change_id'] ?? 0));
        if (!$change) {
            throw new DisplayException('No change with this id on this server (see file_changes).');
        }
        try {
            $this->files->revert($server, $user, $change, (bool) ($arguments['force'] ?? false));
        } catch (\Pterodactyl\Services\Assistant\ChangedSinceException $exception) {
            throw new DisplayException($exception->getMessage() . ' Call again with force=true to do it anyway.');
        }

        return "Done: {$change->path} is back as it was before change {$change->id}.";
    }

    private function sendCommand(User $user, Server $server, string $command): string
    {
        $command = trim($command);
        if ($command === '') {
            throw new DisplayException('Give the command.');
        }
        $this->commands->setServer($server)->send($command);
        Activity::event('server:console.command')->subject($server)->actor($user)->property('command', $command)->log();
        // A moment for the server to answer, then what it printed.
        usleep(700000);
        $this->console->setServer($server);
        $lines = $this->console->lines(15);

        return "Sent: $command\nLast lines of the console:\n" . $this->assistant->cleanConsole(implode("\n", $lines));
    }

    private function powerAction(User $user, array $arguments): string
    {
        $action = (string) ($arguments['action'] ?? '');
        $permission = match ($action) {
            'start' => Permission::ACTION_CONTROL_START,
            'stop', 'kill' => Permission::ACTION_CONTROL_STOP,
            'restart' => Permission::ACTION_CONTROL_RESTART,
            default => throw new DisplayException('The action is start, stop, restart or kill.'),
        };
        $server = $this->server($user, $arguments, $permission);
        $this->power->setServer($server)->send($action);
        Activity::event('server:power.' . $action)->subject($server)->actor($user)->log();

        return "The server {$server->name} was asked to $action. Use server_status or read_console in a few seconds to follow it.";
    }

    /**
     * What Wings says of the server now (empty when the node does not answer).
     */
    private function live(Server $server): array
    {
        try {
            $details = $this->daemon->setServer($server)->getDetails();
        } catch (\Throwable) {
            return [];
        }
        $usage = (array) ($details['utilization'] ?? []);

        return [
            'state' => (string) ($details['state'] ?? 'unknown'),
            'cpu' => (float) ($usage['cpu_absolute'] ?? 0),
            'memory' => (int) ($usage['memory_bytes'] ?? 0),
            'disk' => (int) ($usage['disk_bytes'] ?? 0),
            'uptime' => (int) ($usage['uptime'] ?? 0),
        ];
    }

    private function bytes(int $bytes): string
    {
        $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
        $i = $bytes > 0 ? min(4, (int) floor(log($bytes, 1024))) : 0;

        return round($bytes / (1024 ** $i), 1) . ' ' . $units[$i];
    }
}
