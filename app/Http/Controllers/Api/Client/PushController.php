<?php

namespace Pterodactyl\Http\Controllers\Api\Client;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Models\PushSubscription;
use Pterodactyl\Services\Notifications\WebPush;

/**
 * The notifications of the panel on a device of the person (phone, computer): the key of the panel, turning them on
 * or off for this device, and a test.
 */
class PushController extends ClientApiController
{
    public function __construct(private WebPush $push)
    {
        parent::__construct();
    }

    public function show(Request $request): JsonResponse
    {
        return new JsonResponse([
            'public_key' => $this->push->publicKey(),
            'devices' => PushSubscription::query()->where('user_id', $request->user()->id)->count(),
        ]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:1000', 'url', 'starts_with:https://'],
            'keys.p256dh' => ['required', 'string', 'max:200'],
            'keys.auth' => ['required', 'string', 'max:100'],
        ]);
        if (strlen(WebPush::base64UrlDecode($data['keys']['p256dh'])) !== 65) {
            throw new DisplayException('The keys of this device are not valid.');
        }

        PushSubscription::query()->updateOrCreate(
            ['endpoint_hash' => hash('sha256', $data['endpoint'])],
            [
                'user_id' => $request->user()->id,
                'endpoint' => $data['endpoint'],
                'p256dh' => $data['keys']['p256dh'],
                'auth' => $data['keys']['auth'],
                'device' => Str::limit((string) $request->userAgent(), 115, ''),
            ]
        );

        return new JsonResponse(['devices' => PushSubscription::query()->where('user_id', $request->user()->id)->count()]);
    }

    public function unsubscribe(Request $request): Response
    {
        $endpoint = (string) $request->input('endpoint', '');
        PushSubscription::query()->where('user_id', $request->user()->id)->where('endpoint_hash', hash('sha256', $endpoint))->delete();

        return $this->returnNoContent();
    }

    public function test(Request $request): JsonResponse
    {
        $french = str_starts_with((string) $request->user()->language, 'fr');
        $sent = $this->push->toUser(
            $request->user()->id,
            $french ? 'Notifications activées' : 'Notifications are on',
            $french ? 'Vous serez prévenu ici quand un de vos serveurs plante ou redémarre.' : 'You will be told here when one of your servers crashes or restarts.',
            '/',
            'success'
        );
        if ($sent === 0) {
            throw new DisplayException('No device got the test: turn the notifications on again on this device.');
        }

        return new JsonResponse(['sent' => $sent]);
    }
}
