<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Models\AssistantFileChange;
use Pterodactyl\Models\Permission;
use Pterodactyl\Models\Server;
use Pterodactyl\Services\Assistant\AssistantFiles;
use Pterodactyl\Services\Assistant\AssistantSettings;
use Pterodactyl\Services\Assistant\ServerAssistant;

/**
 * The AI assistant of a server, for the people who can see its console: the questions, and the changes of files it
 * prepares (applied, or put back, only when the person clicks).
 */
class AssistantController extends ClientApiController
{
    public function __construct(private AssistantSettings $settings, private ServerAssistant $assistant, private AssistantFiles $files)
    {
        parent::__construct();
    }

    public function show(Request $request, Server $server): JsonResponse
    {
        $user = $request->user();
        $allowed = $this->settings->allows($user) && $user->can(Permission::ACTION_WEBSOCKET_CONNECT, $server);

        return new JsonResponse([
            'enabled' => $allowed,
            'remaining' => $allowed ? $this->assistant->remaining($user) : null,
        ]);
    }

    public function ask(Request $request, Server $server): JsonResponse
    {
        abort_unless($request->user()->can(Permission::ACTION_WEBSOCKET_CONNECT, $server), 403);
        $data = $request->validate([
            'messages' => ['required', 'array', 'min:1', 'max:30'],
            'messages.*.role' => ['required', 'string', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string', 'max:20000'],
            'console' => ['nullable', 'string', 'max:200000'],
            'status' => ['nullable', 'string', 'max:20'],
        ]);

        $result = $this->assistant->ask($server, $request->user(), $data['messages'], (string) ($data['console'] ?? ''), $data['status'] ?? null);

        return new JsonResponse([
            'answer' => $result['answer'],
            'changes' => array_map(fn (AssistantFileChange $c) => $this->files->present($c), $result['changes']),
            'remaining' => $this->assistant->remaining($request->user()),
        ]);
    }

    /**
     * The changes of files prepared by the assistant on this server (the last 30 days).
     */
    public function changes(Request $request, Server $server): JsonResponse
    {
        abort_unless($request->user()->can(Permission::ACTION_FILE_READ, $server), 403);

        return new JsonResponse(['data' => $this->files->history($server)->map(fn (AssistantFileChange $c) => $this->files->present($c))->all()]);
    }

    public function apply(Request $request, Server $server, int $change): JsonResponse
    {
        $record = $this->find($server, $change);

        return new JsonResponse(['data' => $this->files->present($this->files->apply($server, $request->user(), $record))]);
    }

    public function revert(Request $request, Server $server, int $change): JsonResponse
    {
        $record = $this->find($server, $change);

        return new JsonResponse(['data' => $this->files->present($this->files->revert($server, $request->user(), $record, $request->boolean('force')))]);
    }

    public function dismiss(Request $request, Server $server, int $change): JsonResponse
    {
        abort_unless($request->user()->can(Permission::ACTION_FILE_READ, $server), 403);
        $record = $this->find($server, $change);
        if ($record->state !== AssistantFileChange::PROPOSED) {
            throw new DisplayException('This change was already applied or refused.');
        }
        $record->forceFill(['state' => AssistantFileChange::DISMISSED])->save();

        return new JsonResponse(['data' => $this->files->present($record)]);
    }

    private function find(Server $server, int $id): AssistantFileChange
    {
        /** @var AssistantFileChange $change */
        $change = AssistantFileChange::query()->where('server_id', $server->id)->findOrFail($id);

        return $change;
    }
}
