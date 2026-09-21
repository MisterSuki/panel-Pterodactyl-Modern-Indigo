<?php

namespace Pterodactyl\Http\Controllers\Base;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Web\WebHostingService;
use Pterodactyl\Services\Web\WebHostingSettings;

/**
 * Gives the web server (Caddy) the list of the domains it has to serve and where each one goes. The web server fetches it
 * by itself, with the token that the administration made for it. Anyone else, or the hosting being off, gets a plain
 * "not found", as if this address did not exist.
 */
class ProxyConfigController extends Controller
{
    public function __construct(private WebHostingService $hosting, private WebHostingSettings $settings)
    {
    }

    public function show(Request $request): Response
    {
        $token = $request->bearerToken() ?: $request->header('X-Panel-Token');
        if (!$this->settings->enabled() || !$this->settings->tokenIsValid(is_string($token) ? $token : null)) {
            abort(404);
        }

        $config = $this->hosting->proxyConfig();
        $etag = '"' . md5($config) . '"';
        if ($request->header('If-None-Match') === $etag) {
            return response('', 304, ['ETag' => $etag]);
        }

        return response($config, 200, ['Content-Type' => 'text/plain; charset=utf-8', 'ETag' => $etag, 'Cache-Control' => 'no-store']);
    }
}
