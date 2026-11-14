<?php

namespace Pterodactyl\Http\Controllers\Admin\Nodes;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Node;
use Pterodactyl\Services\Nodes\WingsInstaller;

/**
 * The automatic installation of Wings from the configuration page of a node: start it, and follow it.
 */
class WingsInstallController extends Controller
{
    public function __construct(private WingsInstaller $installer)
    {
    }

    public function status(Node $node): JsonResponse
    {
        return new JsonResponse($this->installer->status($node));
    }

    public function start(Request $request, Node $node): JsonResponse
    {
        $data = $request->validate([
            'host' => ['required', 'string', 'max:191', 'regex:/^[A-Za-z0-9.\-:\[\]]+$/'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'user' => ['required', 'string', 'max:32', 'regex:/^[a-z_][a-z0-9_.-]*$/'],
            'method' => ['required', 'in:password,key'],
            'password' => ['nullable', 'required_if:method,password', 'string', 'max:500'],
        ]);

        $this->installer->start(
            $node,
            $request->user(),
            trim($data['host'], '[]'),
            (int) $data['port'],
            $data['user'],
            $data['method'] === 'password' ? (string) $data['password'] : null,
        );

        return new JsonResponse($this->installer->status($node));
    }
}
