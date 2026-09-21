<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\User;
use Pterodactyl\Services\Support\ScreenShareService;

/**
 * The side of the staff who ask to see the screen of a person (see ScreenShareService). Only staff who can manage users
 * may ask, the person has to accept, and every request is written in the activity log.
 */
class ScreenShareController extends Controller
{
    public function __construct(private ScreenShareService $screens)
    {
    }

    /**
     * Asks the person to share their screen.
     */
    public function request(Request $request, int $id): JsonResponse
    {
        $admin = $this->allowed($request);
        $target = User::query()->findOrFail($id);

        $session = $this->screens->request($target, $admin);
        Activity::event('admin:screen.request')->subject($target)->property('username', $target->username)->log();

        return new JsonResponse(['object' => 'screen_session', 'data' => $this->screens->forAdmin($session)], 201);
    }

    /**
     * Where things stand: waiting, refused, offered, connected or over.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $admin = $this->allowed($request);
        $session = $this->screens->get($id);
        if ($session && $session['admin_id'] !== $admin->id) {
            $session = null;
        }

        return new JsonResponse(['object' => 'screen_session', 'data' => $this->screens->forAdmin($session)]);
    }

    /**
     * The answer to the offer of the person.
     */
    public function answer(Request $request, int $id): JsonResponse
    {
        $admin = $this->allowed($request);
        $request->validate(['id' => ['required', 'string', 'max:64'], 'sdp' => ['required', 'string', 'max:' . ScreenShareService::MAX_SDP]]);

        $this->screens->answer($id, $admin, (string) $request->input('id'), (string) $request->input('sdp'));

        return new JsonResponse([], 204);
    }

    /**
     * The staff member stops looking.
     */
    public function stop(Request $request, int $id): JsonResponse
    {
        $admin = $this->allowed($request);
        $this->screens->stop($id, $admin);

        return new JsonResponse([], 204);
    }

    private function allowed(Request $request): User
    {
        $admin = $request->user();
        abort_unless($admin->hasAdminPermission('users.manage'), 403);

        return $admin;
    }
}
