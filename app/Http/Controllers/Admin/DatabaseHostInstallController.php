<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Databases\Hosts\DatabaseHostInstaller;

/**
 * The automatic creation of a database host from its page: start it, and follow it.
 */
class DatabaseHostInstallController extends Controller
{
    public function __construct(private DatabaseHostInstaller $installer)
    {
    }

    public function status(): JsonResponse
    {
        return new JsonResponse($this->installer->status());
    }

    public function start(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:1', 'max:60'],
            'node_id' => ['nullable', 'integer', 'exists:nodes,id'],
            'host' => ['required', 'string', 'max:191', 'regex:/^[A-Za-z0-9.\-:\[\]]+$/'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'user' => ['required', 'string', 'max:32', 'regex:/^[a-z_][a-z0-9_.-]*$/'],
            'method' => ['required', 'in:password,key'],
            'password' => ['nullable', 'required_if:method,password', 'string', 'max:500'],
            'db_host' => ['nullable', 'string', 'max:191', 'regex:/^[A-Za-z0-9.\-:\[\]]+$/'],
            'db_user' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]{2,31}$/'],
        ]);

        $host = trim($data['host'], '[]');
        $this->installer->start([
            'name' => $data['name'],
            'node_id' => isset($data['node_id']) ? (int) $data['node_id'] : null,
            'host' => $host,
            'port' => (int) $data['port'],
            'user' => $data['user'],
            'password' => $data['method'] === 'password' ? (string) $data['password'] : null,
            'db_host' => trim((string) ($data['db_host'] ?? '') ?: $host, '[]'),
            'db_user' => $data['db_user'],
        ]);

        return new JsonResponse($this->installer->status());
    }
}
