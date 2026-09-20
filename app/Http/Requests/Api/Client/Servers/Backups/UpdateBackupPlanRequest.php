<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Backups;

use Illuminate\Validation\Rule;
use Pterodactyl\Models\Permission;
use Pterodactyl\Services\Backups\AutoBackupService;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class UpdateBackupPlanRequest extends ClientApiRequest
{
    /**
     * Setting up automatic backups is creating backups, so it needs the same permission.
     */
    public function permission(): string
    {
        return Permission::ACTION_BACKUP_CREATE;
    }

    public function rules(): array
    {
        return [
            'enabled' => 'sometimes|boolean',
            'frequency' => ['required', 'string', Rule::in(array_keys(AutoBackupService::FREQUENCIES))],
            'hour' => 'nullable|integer|between:0,23',
            'keep' => 'required|integer|min:1|max:1000',
            'ignored' => 'nullable|string|max:2000',
        ];
    }
}
