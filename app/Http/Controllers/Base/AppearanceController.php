<?php

namespace Pterodactyl\Http\Controllers\Base;

use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Helpers\Appearance;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AppearanceController extends Controller
{
    public function __construct(private Appearance $appearance)
    {
    }

    /**
     * The background picture chosen by the administration. Everybody gets it, visitors on the sign-in page included.
     * Its address changes with each new picture, so browsers may keep it for long.
     */
    public function background(): BinaryFileResponse
    {
        $path = $this->appearance->imagePath();
        if (!$path) {
            throw new NotFoundHttpException();
        }

        $response = response()->file($path, [
            'Content-Type' => Appearance::MIME[pathinfo($path, PATHINFO_EXTENSION)] ?? 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
        $response->setPublic();
        $response->setMaxAge(31536000);

        return $response;
    }
}
