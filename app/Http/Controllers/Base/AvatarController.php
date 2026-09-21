<?php

namespace Pterodactyl\Http\Controllers\Base;

use Illuminate\Http\RedirectResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\User;
use Pterodactyl\Services\Users\AvatarService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AvatarController extends Controller
{
    public function __construct(private AvatarService $avatars)
    {
    }

    /**
     * The profile picture of a person, for the people who are signed in. Someone who kept the default one is sent to it.
     */
    public function show(string $uuid): BinaryFileResponse|RedirectResponse
    {
        $user = User::query()->where('uuid', $uuid)->first();
        $path = $user ? $this->avatars->path($user) : null;
        if (!$path) {
            return redirect(User::DEFAULT_AVATAR);
        }

        $response = response()->file($path, [
            'Content-Type' => AvatarService::MIME[pathinfo($path, PATHINFO_EXTENSION)] ?? 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        // Kept by the browser of the person who asked, never by a cache that is shared.
        $response->setPrivate();
        $response->setMaxAge(86400);

        return $response;
    }
}
