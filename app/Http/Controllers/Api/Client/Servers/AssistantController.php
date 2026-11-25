<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Models\Permission;
use Pterodactyl\Models\Server;
use Pterodactyl\Services\Assistant\AssistantSettings;
use Pterodactyl\Services\Assistant\ServerAssistant;

/**
 * The AI assistant of a server, for the people who can see its console.
 */
class AssistantController extends ClientApiController
{
    public function __construct(private AssistantSettings $settings, private ServerAssistant $assistant)
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

        $answer = $this->assistant->ask($server, $request->user(), $data['messages'], (string) ($data['console'] ?? ''), $data['status'] ?? null);

        return new JsonResponse([
            'answer' => $answer,
            'remaining' => $this->assistant->remaining($request->user()),
        ]);
    }
}
