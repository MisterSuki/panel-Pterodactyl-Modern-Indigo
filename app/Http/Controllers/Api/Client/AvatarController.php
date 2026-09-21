<?php

namespace Pterodactyl\Http\Controllers\Api\Client;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Services\Users\AvatarService;

class AvatarController extends ClientApiController
{
    public function __construct(private AvatarService $avatars)
    {
        parent::__construct();
    }

    /**
     * Sets the profile picture of the person who is signed in.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate(['avatar' => ['required', 'file', 'max:2048', 'mimes:png,jpg,jpeg,webp']]);

        $user = $request->user();
        $this->avatars->store($user, $request->file('avatar'));
        Activity::event('user:account.avatar-changed')->log();

        return new JsonResponse(['avatar' => $user->avatarUrl()]);
    }

    /**
     * Goes back to the default picture.
     */
    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->avatars->remove($user);
        Activity::event('user:account.avatar-removed')->log();

        return new JsonResponse(['avatar' => $user->avatarUrl()]);
    }
}
