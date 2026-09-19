<?php

namespace Pterodactyl\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Pterodactyl\Rules\Username;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Services\Auth\AuthFeatures;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Users\UserRegistrationService;

class RegisterController extends AbstractLoginController
{
    public function __construct(private UserRegistrationService $registration)
    {
        parent::__construct();
    }

    /**
     * Handle a self-registration request. The new account is logged in right away.
     *
     * @throws \Pterodactyl\Exceptions\DisplayException
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     */
    public function __invoke(Request $request): JsonResponse
    {
        if (!AuthFeatures::registrationEnabled()) {
            throw new DisplayException('Registration is currently disabled on this Panel.');
        }

        $request->merge([
            'username' => mb_strtolower(trim((string) $request->input('username'))),
            'email' => mb_strtolower(trim((string) $request->input('email'))),
        ]);

        $data = $request->validate([
            'username' => ['required', 'string', 'between:3,191', new Username(), 'unique:users,username'],
            'email' => 'required|email:strict|between:1,191|unique:users,email',
            'name_first' => 'required|string|between:1,191',
            'name_last' => 'required|string|between:1,191',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = $this->registration->handle($data);

        return $this->sendLoginResponse($user, $request);
    }
}
