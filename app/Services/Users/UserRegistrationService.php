<?php

namespace Pterodactyl\Services\Users;

use Ramsey\Uuid\Uuid;
use Pterodactyl\Models\User;
use Pterodactyl\Facades\Activity;
use Illuminate\Contracts\Hashing\Hasher;

/**
 * Creates accounts for people who sign up themselves, either with the registration form
 * or through Discord. Unlike UserCreationService this never sends an "account created"
 * email, since the person creating the account is the one who will use it.
 */
class UserRegistrationService
{
    public function __construct(private Hasher $hasher)
    {
    }

    /**
     * @param array{username: string, email: string, name_first: string, name_last: string, password: string, discord_id?: string|null, discord_username?: string|null} $data
     *
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     */
    public function handle(array $data): User
    {
        $user = new User();
        $user->forceFill([
            'uuid' => Uuid::uuid4()->toString(),
            'username' => $data['username'],
            'email' => $data['email'],
            'name_first' => $data['name_first'],
            'name_last' => $data['name_last'],
            'password' => $this->hasher->make($data['password']),
            'discord_id' => $data['discord_id'] ?? null,
            'discord_username' => $data['discord_username'] ?? null,
        ]);
        $user->save();

        Activity::event('user:user.create')
            ->subject($user)
            ->property([
                'email' => $user->email,
                'username' => $user->username,
                'admin' => false,
            ])
            ->log('self-registration');

        return $user;
    }
}
