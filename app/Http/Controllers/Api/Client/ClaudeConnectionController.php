<?php

namespace Pterodactyl\Http\Controllers\Api\Client;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Models\ClaudeConnection;

/**
 * The links of the person to connect the Claude app to the panel: create one (its address is shown once), see them,
 * delete one (Claude loses the access at once).
 */
class ClaudeConnectionController extends ClientApiController
{
    private const MAX = 10;

    public function index(Request $request): JsonResponse
    {
        return new JsonResponse(['data' => ClaudeConnection::query()->where('user_id', $request->user()->id)->orderByDesc('id')->get()
            ->map(fn (ClaudeConnection $c) => $this->present($c))->all()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:60'], 'read_only' => ['required', 'boolean']]);
        $user = $request->user();
        if (ClaudeConnection::query()->where('user_id', $user->id)->count() >= self::MAX) {
            throw new DisplayException('You have ' . self::MAX . ' links already: delete one first.');
        }

        $token = Str::random(48);
        /** @var ClaudeConnection $connection */
        $connection = ClaudeConnection::query()->create([
            'user_id' => $user->id,
            'name' => trim($data['name']),
            'token_hash' => hash('sha256', $token),
            'read_only' => (bool) $data['read_only'],
        ]);
        Activity::event('user:claude.create')->property(['name' => $connection->name, 'read_only' => $connection->read_only])->log();

        return new JsonResponse(['data' => $this->present($connection) + ['url' => rtrim((string) config('app.url'), '/') . '/mcp/' . $token]]);
    }

    public function delete(Request $request, int $id): Response
    {
        $connection = ClaudeConnection::query()->where('user_id', $request->user()->id)->findOrFail($id);
        $connection->delete();
        Activity::event('user:claude.delete')->property(['name' => $connection->name])->log();

        return $this->returnNoContent();
    }

    private function present(ClaudeConnection $connection): array
    {
        return [
            'id' => $connection->id,
            'name' => $connection->name,
            'read_only' => $connection->read_only,
            'last_used_at' => $connection->last_used_at?->toAtomString(),
            'created_at' => $connection->created_at?->toAtomString(),
        ];
    }
}
