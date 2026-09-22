<?php

namespace Pterodactyl\Http\Requests\Base;

use Illuminate\Foundation\Http\FormRequest;

class LocaleRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // One language or several ("fr en"): the browser asks for its language and the fallback at once.
            'locale' => ['required', 'string', 'regex:/^[a-z][a-z]( [a-z][a-z]){0,3}$/'],
            'namespace' => ['required', 'string', 'regex:/^[a-z]{1,64}( [a-z]{1,64}){0,7}$/'],
        ];
    }
}
