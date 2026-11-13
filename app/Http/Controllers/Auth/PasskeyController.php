<?php

namespace Pterodactyl\Http\Controllers\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Services\Auth\Passkeys;

/**
 * Signing in with a passkey. The device unlocks it with the face, a finger or a PIN, so it is already two factors (the
 * device and the person): the code of the authenticator app is not asked after it.
 */
class PasskeyController extends AbstractLoginController
{
    public function __construct(private Passkeys $passkeys)
    {
        parent::__construct();
    }

    public function options(Request $request): JsonResponse
    {
        return new JsonResponse($this->passkeys->loginOptions($request));
    }

    /**
     * @throws DisplayException
     */
    public function login(Request $request): JsonResponse
    {
        if ($this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);
            $this->sendLockoutResponse($request);
        }

        $data = $request->validate([
            'id' => ['required', 'string', 'max:512'],
            'client_data' => ['required', 'string', 'max:8192'],
            'authenticator_data' => ['required', 'string', 'max:8192'],
            'signature' => ['required', 'string', 'max:2048'],
            'user_handle' => ['nullable', 'string', 'max:512'],
        ]);

        try {
            $user = $this->passkeys->login($request, $data);
        } catch (DisplayException $exception) {
            $this->incrementLoginAttempts($request);
            $this->fireFailedLoginEvent(null, ['passkey' => mb_substr($data['id'], 0, 32)]);

            throw $exception;
        }

        Activity::event('auth:passkey')->withRequestMetadata()->subject($user)->log();

        return $this->sendLoginResponse($user, $request);
    }
}
