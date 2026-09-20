<?php

namespace Pterodactyl\Http\Requests\Admin\Settings;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Pterodactyl\Services\Helpers\Locales;
use Pterodactyl\Services\Auth\AuthFeatures;
use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class BaseSettingsFormRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'app:name' => 'required|string|max:191',
            'app:locale' => ['required', 'string', Rule::in(Locales::codes())],
            'pterodactyl:copyright:text' => 'nullable|string|max:191',
            'pterodactyl:copyright:url' => 'nullable|url|max:191',
            'pterodactyl:auth:2fa_required' => 'required|integer|in:0,1,2',
            'pterodactyl:auth:registration' => 'required|in:true,false',
            'pterodactyl:auth:discord:enabled' => 'required|in:true,false',
            'pterodactyl:auth:discord:client_id' => 'required_if:pterodactyl:auth:discord:enabled,true|nullable|string|max:64',
            'pterodactyl:auth:discord:client_secret' => 'nullable|string|max:191',
        ];
    }

    public function attributes(): array
    {
        return [
            'app:name' => 'Company Name',
            'app:locale' => 'Language',
            'pterodactyl:copyright:text' => 'Copyright',
            'pterodactyl:copyright:url' => 'Copyright Link',
            'pterodactyl:auth:2fa_required' => 'Require 2-Factor Authentication',
            'pterodactyl:auth:registration' => 'Allow Registration',
            'pterodactyl:auth:discord:enabled' => 'Discord Login',
            'pterodactyl:auth:discord:client_id' => 'Discord Client ID',
            'pterodactyl:auth:discord:client_secret' => 'Discord Client Secret',
        ];
    }

    /**
     * The client secret is never shown again once saved, so leaving the field empty keeps
     * the current one. It is only required when there is no secret to fall back on.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $enabled = $this->input('pterodactyl:auth:discord:enabled') === 'true';

            if ($enabled && empty($this->input('pterodactyl:auth:discord:client_secret')) && empty(AuthFeatures::discordClientSecret())) {
                $validator->errors()->add('pterodactyl:auth:discord:client_secret', 'A Discord Client Secret is required to enable Discord login.');
            }
        });
    }
}
