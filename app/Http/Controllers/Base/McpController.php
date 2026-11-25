<?php

namespace Pterodactyl\Http\Controllers\Base;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\ClaudeConnection;
use Pterodactyl\Services\Claude\PanelMcp;

/**
 * The connector of the Claude app (/mcp/<secret>): JSON-RPC messages of the Model Context Protocol over HTTP
 * ("Streamable HTTP", answered in JSON, no stream). The secret of the link is the authentication: it acts as its owner.
 */
class McpController extends Controller
{
    public function __construct(private PanelMcp $mcp)
    {
    }

    public function handle(Request $request, string $token): Response|JsonResponse
    {
        $connection = $this->connection($token);
        if (!$connection) {
            return new JsonResponse(['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32001, 'message' => 'This link of Claude does not exist any more. Create a new one in the panel (Account > Claude).']], 401);
        }
        $user = $connection->user;
        // The tools act as the owner of the link: rights, activity log.
        Auth::guard()->setUser($user);
        $request->setUserResolver(fn () => $user);
        if ($connection->last_used_at === null || $connection->last_used_at->lt(now()->subMinutes(5))) {
            $connection->forceFill(['last_used_at' => now()])->save();
        }

        $body = json_decode($request->getContent(), true);
        if (!is_array($body)) {
            return new JsonResponse(['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32700, 'message' => 'Parse error']], 400);
        }

        // One message, or a batch of them.
        $batch = array_is_list($body);
        $answers = [];
        foreach ($batch ? $body : [$body] as $message) {
            if (!is_array($message)) {
                $answers[] = ['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32600, 'message' => 'Invalid request']];
                continue;
            }
            $answer = $this->mcp->handle($message, $connection);
            if ($answer !== null) {
                $answers[] = $answer;
            }
        }

        if ($answers === []) {
            // Only notifications: accepted, nothing to say.
            return new Response('', 202);
        }

        return new JsonResponse($batch ? $answers : $answers[0], 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * No stream of messages from the panel: Claude only asks.
     */
    public function stream(): Response
    {
        return new Response('', 405, ['Allow' => 'POST']);
    }

    private function connection(string $token): ?ClaudeConnection
    {
        if (preg_match('/^[A-Za-z0-9]{48}$/', $token) !== 1) {
            return null;
        }

        /** @var ClaudeConnection|null $connection */
        $connection = ClaudeConnection::query()->with('user')->where('token_hash', hash('sha256', $token))->first();

        return $connection;
    }
}
