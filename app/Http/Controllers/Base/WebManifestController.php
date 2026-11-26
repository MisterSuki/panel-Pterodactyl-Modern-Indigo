<?php

namespace Pterodactyl\Http\Controllers\Base;

use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;

/**
 * The manifest of the panel as an app (PWA): installed on a phone or a computer, it opens alone, with its name and
 * icon, and can show the notifications of the panel.
 */
class WebManifestController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $name = (string) config('app.name', 'Pterodactyl');

        return new JsonResponse([
            'id' => '/',
            'name' => $name,
            'short_name' => mb_substr($name, 0, 12),
            'description' => 'Your servers: console, files, backups and alerts.',
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => '#0a0a0a',
            'theme_color' => '#0a0a0a',
            'icons' => [
                ['src' => '/favicons/android-chrome-192x192.png', 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => '/favicons/android-chrome-512x512.png', 'sizes' => '512x512', 'type' => 'image/png'],
                ['src' => '/favicons/android-chrome-512x512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'public, max-age=3600'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
