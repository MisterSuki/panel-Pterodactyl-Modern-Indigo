<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Subusers;

use Pterodactyl\Models\Permission;
use Pterodactyl\Services\Subusers\SubuserInviteResolver;

class StoreSubuserRequest extends SubuserRequest
{
    public function permission(): string
    {
        return Permission::ACTION_USER_CREATE;
    }

    public function rules(): array
    {
        return [
            // A person is invited either by email, or by the Discord ID of an account that already exists.
            'email' => 'required_without:discord_id|prohibits:discord_id|nullable|email:strict|between:1,191',
            'discord_id' => ['required_without:email', 'prohibits:email', 'nullable', 'regex:' . SubuserInviteResolver::DISCORD_ID_PATTERN],
            'permissions' => 'required|array',
            'permissions.*' => 'string',
        ];
    }
}
