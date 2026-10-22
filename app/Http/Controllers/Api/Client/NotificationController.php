<?php

namespace Pterodactyl\Http\Controllers\Api\Client;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Pterodactyl\Services\Notifications\Notifier;

/**
 * The bell of the panel: the messages of the person signed in, and marking them read.
 */
class NotificationController extends ClientApiController
{
    public function __construct(private Notifier $notifier)
    {
        parent::__construct();
    }

    public function index(Request $request): JsonResponse
    {
        return new JsonResponse($this->notifier->forUser($request->user()));
    }

    public function read(Request $request): JsonResponse
    {
        $data = $request->validate(['ids' => ['nullable', 'array', 'max:100'], 'ids.*' => ['integer']]);
        $this->notifier->markRead($request->user(), isset($data['ids']) ? array_map('intval', $data['ids']) : null);

        return new JsonResponse($this->notifier->forUser($request->user()));
    }
}
