<?php

namespace Pterodactyl\Http\Controllers\Api\Client;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Services\Support\ScreenShareService;

/**
 * The side of the person who is asked to share their screen (see ScreenShareService). Everything here is about their own
 * session: nobody can reach the session of somebody else.
 */
class ScreenShareController extends ClientApiController
{
    public function __construct(private ScreenShareService $screens)
    {
        parent::__construct();
    }

    /**
     * Is somebody asking to see the screen? The dashboard asks every few seconds.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->screens->touch($user->id);

        return new JsonResponse(['object' => 'screen_request', 'data' => $this->screens->forUser($this->screens->get($user->id))]);
    }

    /**
     * The person accepted and their browser sends what the other browser needs to connect.
     */
    public function offer(Request $request): JsonResponse
    {
        $request->validate(['sdp' => ['required', 'string', 'max:' . ScreenShareService::MAX_SDP]]);

        $user = $request->user();
        $this->screens->offer($user, (string) $request->input('sdp'));
        Activity::event('user:screen.share')->log();

        return new JsonResponse([], 204);
    }

    /**
     * The person does not want to share.
     */
    public function decline(Request $request): JsonResponse
    {
        $this->screens->decline($request->user());
        Activity::event('user:screen.decline')->log();

        return new JsonResponse([], 204);
    }

    /**
     * The person stops sharing.
     */
    public function stop(Request $request): JsonResponse
    {
        $user = $request->user();
        $session = $this->screens->get($user->id);
        if ($session && in_array($session['state'], ['offered', 'connected'], true)) {
            $this->screens->stop($user->id);
            Activity::event('user:screen.stop')->log();
        } elseif ($session && $session['state'] === 'requested') {
            $this->screens->stop($user->id);
        }

        return new JsonResponse([], 204);
    }
}
