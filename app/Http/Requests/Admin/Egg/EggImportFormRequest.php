<?php

namespace Pterodactyl\Http\Requests\Admin\Egg;

use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class EggImportFormRequest extends AdminFormRequest
{
    public function rules(): array
    {
        $rules = [
            // Some systems give a .json file another type: the content is checked anyway when it is read.
            'import_file' => 'bail|required|file|max:2048|mimetypes:application/json,text/plain,text/x-json,application/octet-stream',
        ];

        if ($this->method() !== 'PUT') {
            $rules['import_to_nest'] = 'bail|required|integer|exists:nests,id';
        }

        return $rules;
    }
}
