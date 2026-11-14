<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Databases\PhpMyAdminInstaller;
use Pterodactyl\Services\Databases\PhpMyAdminSignOn;

/**
 * The "Install phpMyAdmin" button of the Database Hosts page: start it, and follow it.
 */
class PhpMyAdminInstallController extends Controller
{
    public function __construct(private PhpMyAdminInstaller $installer)
    {
    }

    public function status(): JsonResponse
    {
        return new JsonResponse(array_merge($this->installer->status(), ['installed' => PhpMyAdminSignOn::enabled()]));
    }

    public function start(Request $request): JsonResponse
    {
        $data = $request->validate([
            'host' => ['required', 'string', 'max:191', 'regex:/^[A-Za-z0-9.\-:\[\]]+$/'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'user' => ['required', 'string', 'max:32', 'regex:/^[a-z_][a-z0-9_.-]*$/'],
            'method' => ['required', 'in:password,key'],
            'password' => ['nullable', 'required_if:method,password', 'string', 'max:500'],
        ]);

        $this->installer->launch([
            'host' => trim($data['host'], '[]'),
            'port' => (int) $data['port'],
            'user' => $data['user'],
            'password' => $data['method'] === 'password' ? (string) $data['password'] : null,
            // The folder of this panel, so the installer finds it on its machine.
            'path' => base_path(),
        ]);

        return $this->status();
    }
}
