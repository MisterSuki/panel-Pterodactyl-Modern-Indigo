<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Pterodactyl\Models\User;
use Pterodactyl\Models\AdminRole;
use Pterodactyl\Facades\Activity;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Http\Requests\Admin\RoleFormRequest;

class RoleController extends Controller
{
    public function __construct(private AlertsMessageBag $alert)
    {
    }

    /**
     * List every role with the number of people who have it.
     */
    public function index(): View
    {
        return view('admin.roles.index', [
            'roles' => AdminRole::query()->withCount('users')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.roles.new', [
            'catalog' => AdminRole::catalog(),
            'selected' => (array) old('permissions', []),
        ]);
    }

    public function view(AdminRole $role): View
    {
        return view('admin.roles.view', [
            'role' => $role,
            'members' => $role->users()->orderBy('username')->get(),
            'catalog' => AdminRole::catalog(),
            'selected' => (array) old('permissions', $role->permissions),
        ]);
    }

    public function store(RoleFormRequest $request): RedirectResponse
    {
        $role = AdminRole::query()->create($request->normalize());

        Activity::event('admin:role.create')->property(['name' => $role->name, 'permissions' => $role->permissions])->log();
        $this->alert->success('The role "' . $role->name . '" was created. Add people to it below.')->flash();

        return redirect()->route('admin.roles.view', $role->id);
    }

    public function update(RoleFormRequest $request, AdminRole $role): RedirectResponse
    {
        $role->update($request->normalize());

        Activity::event('admin:role.update')->property(['name' => $role->name, 'permissions' => $role->permissions])->log();
        $this->alert->success('The role "' . $role->name . '" was updated. The change applies to everyone who has it right away.')->flash();

        return redirect()->route('admin.roles.view', $role->id);
    }

    /**
     * Delete a role. The people who had it go back to being regular users.
     */
    public function delete(AdminRole $role): RedirectResponse
    {
        $role->delete();

        Activity::event('admin:role.delete')->property(['name' => $role->name])->log();
        $this->alert->success('The role "' . $role->name . '" was deleted.')->flash();

        return redirect()->route('admin.roles');
    }

    /**
     * Give the role to a user, found by username or email address.
     *
     * @throws DisplayException
     */
    public function addMember(Request $request, AdminRole $role): RedirectResponse
    {
        $identifier = trim((string) $request->validate(['user' => 'required|string|max:191'])['user']);

        $user = User::query()->where('username', mb_strtolower($identifier))->orWhere('email', mb_strtolower($identifier))->first();
        if (!$user) {
            throw new DisplayException('No user was found with that username or email address.');
        }

        if ($user->root_admin) {
            throw new DisplayException('Administrators already have every permission, a role would change nothing.');
        }

        User::query()->whereKey($user->id)->update(['admin_role_id' => $role->id]);

        Activity::event('admin:role.assign')->subject($user)->property(['role' => $role->name])->log();
        $this->alert->success($user->username . ' now has the role "' . $role->name . '".')->flash();

        return redirect()->route('admin.roles.view', $role->id);
    }

    public function removeMember(AdminRole $role, User $user): RedirectResponse
    {
        User::query()->whereKey($user->id)->update(['admin_role_id' => null]);

        Activity::event('admin:role.unassign')->subject($user)->property(['role' => $role->name])->log();
        $this->alert->success($user->username . ' no longer has the role "' . $role->name . '".')->flash();

        return redirect()->route('admin.roles.view', $role->id);
    }
}
