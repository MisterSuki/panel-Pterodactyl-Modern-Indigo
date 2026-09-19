<?php

namespace Pterodactyl\Http\Requests\Admin;

use Illuminate\Validation\Rule;
use Pterodactyl\Models\AdminRole;

class RoleFormRequest extends AdminFormRequest
{
    /**
     * Only full administrators can create or change roles.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->root_admin;
    }

    public function rules(): array
    {
        $role = $this->route()->parameter('role');
        $ignoreId = $role instanceof AdminRole ? $role->id : null;

        return [
            'name' => ['required', 'string', 'between:1,64', Rule::unique('admin_roles', 'name')->ignore($ignoreId)],
            'description' => 'nullable|string|max:191',
            'permissions' => 'nullable|array',
            'permissions.*' => ['string', Rule::in(AdminRole::allPermissions())],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Role Name',
            'description' => 'Description',
            'permissions' => 'Permissions',
        ];
    }

    /**
     * @return array{name: string, description: string|null, permissions: string[]}
     */
    public function normalize(?array $only = null): array
    {
        return [
            'name' => trim((string) $this->input('name')),
            'description' => $this->input('description'),
            'permissions' => AdminRole::normalize((array) $this->input('permissions', [])),
        ];
    }
}
