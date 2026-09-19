<?php

namespace Pterodactyl\Http\Requests\Api\Client\Account;

use Illuminate\Validation\Rule;
use Pterodactyl\Services\Helpers\Locales;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class UpdateLanguageRequest extends ClientApiRequest
{
    public function rules(): array
    {
        return [
            'language' => ['required', 'string', Rule::in(Locales::codes())],
        ];
    }
}
