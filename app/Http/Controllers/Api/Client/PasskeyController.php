<?php

namespace Pterodactyl\Http\Controllers\Api\Client;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Models\UserPasskey;
use Pterodactyl\Services\Auth\Passkeys;

/**
 * The passkeys of the account signed in: the list, adding one from this device, and removing one.
 */
class PasskeyController extends ClientApiController
{
    public function __construct(private Passkeys $passkeys)
    {
        parent::__construct();
    }

    public function index(Request $request): JsonResponse
    {
        return new JsonResponse(['data' => $this->list($request)]);
    }

    public function options(Request $request): JsonResponse
    {
        if ($this->passkeys->of($request->user())->count() >= Passkeys::MAX) {
            throw new DisplayException('An account can have at most ' . Passkeys::MAX . ' passkeys.');
        }

        return new JsonResponse($this->passkeys->registrationOptions($request, $request->user()));
    }

    /**
     * @throws DisplayException
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:64'],
            'id' => ['required', 'string', 'max:512'],
            'client_data' => ['required', 'string', 'max:8192'],
            'authenticator_data' => ['required', 'string', 'max:8192'],
            'public_key' => ['required', 'string', 'max:4096'],
            'algorithm' => ['required', 'integer'],
            'transports' => ['nullable', 'array', 'max:6'],
            'transports.*' => ['string', 'max:16'],
        ]);

        $passkey = $this->passkeys->register($request, $request->user(), $data);
        Activity::event('user:passkey.create')->subject($passkey)->property('name', $passkey->name)->log();

        return new JsonResponse(['data' => $this->list($request)]);
    }

    public function delete(Request $request, int $passkey): JsonResponse
    {
        $model = UserPasskey::query()->where('user_id', $request->user()->id)->findOrFail($passkey);
        $model->delete();
        Activity::event('user:passkey.delete')->subject($model)->property('name', $model->name)->log();

        return new JsonResponse(['data' => $this->list($request)]);
    }

    private function list(Request $request): array
    {
        return $this->passkeys->of($request->user())->map(fn (UserPasskey $p) => [
            'id' => $p->id,
            'name' => $p->name,
            'site' => $p->rp_id,
            'created_at' => $p->created_at?->toAtomString(),
            'last_used_at' => $p->last_used_at?->toAtomString(),
        ])->values()->all();
    }
}
