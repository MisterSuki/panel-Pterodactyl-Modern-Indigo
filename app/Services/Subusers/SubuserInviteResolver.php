<?php

namespace Pterodactyl\Services\Subusers;

use Pterodactyl\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Works out which account a subuser invitation is for.
 *
 * An invitation is given either an email address, which is how Pterodactyl has always done it (an account is
 * created when the address is new), or the Discord ID of a person who already has an account on the panel and
 * has linked Discord to it. A Discord ID never creates an account: the person has to have signed in first.
 */
class SubuserInviteResolver
{
    public const DISCORD_ID_PATTERN = '/^[0-9]{15,25}$/';

    /**
     * The email address of the account to invite.
     *
     * @throws ValidationException
     */
    public function emailFor(?string $email, ?string $discordId): string
    {
        if (!empty($discordId)) {
            $user = User::query()->where('discord_id', $discordId)->first();

            if ($user === null) {
                throw ValidationException::withMessages([
                    'discord_id' => 'No account is linked to that Discord ID. The person has to sign in to the panel with Discord first, or you can invite them by email.',
                ]);
            }

            return $user->email;
        }

        if (empty($email)) {
            throw ValidationException::withMessages(['email' => 'An email address or a Discord ID is required.']);
        }

        return $email;
    }
}
