<?php

namespace Pterodactyl\Http\Controllers\Base;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Services\Helpers\Locales;
use Pterodactyl\Http\Controllers\Controller;

/**
 * The translations of the texts that are written straight into the pages (buttons, titles, help texts).
 * They are kept in resources/lang/<code>.json, with the English text as the key, and applied in the browser.
 * English needs no file: the pages are written in English.
 */
class UiLocaleController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $locale = (string) $request->query('locale', '');
        $file = resource_path("lang/{$locale}.json");

        $data = [];
        if (preg_match('/^[a-z]{2}$/', $locale) === 1 && Locales::isAvailable($locale) && is_file($file)) {
            $decoded = json_decode((string) file_get_contents($file), true);
            $data = is_array($decoded) ? $decoded : [];
        }

        return new JsonResponse($data, 200, [
            // Kept an hour in the browser, and a stale copy may be used for a day while it is refreshed.
            'Cache-Control' => 'public, max-age=3600, stale-while-revalidate=86400',
            'ETag' => md5((string) json_encode($data)),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
