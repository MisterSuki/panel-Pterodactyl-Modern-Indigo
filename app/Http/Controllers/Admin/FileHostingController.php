<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\User;
use Pterodactyl\Models\UserFile;
use Pterodactyl\Services\Files\FileHosting;

/**
 * The file hosting in the administration: open it, its limits, the space of one user, and the files kept (to remove
 * one that should not be there).
 */
class FileHostingController extends Controller
{
    public function __construct(private FileHosting $hosting, private AlertsMessageBag $alert)
    {
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $files = UserFile::query()->with('user:id,username,email')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%')
                    ->orWhereHas('user', fn ($user) => $user->where('username', 'like', '%' . $search . '%')->orWhere('email', 'like', '%' . $search . '%'));
            }))
            ->orderByDesc('id')->paginate(50)->withQueryString();

        return view('admin.files.index', [
            'config' => $this->hosting->config(),
            'files' => $files,
            'search' => $search,
            'hosting' => $this->hosting,
            'stats' => [
                'count' => UserFile::query()->count(),
                'size' => (int) UserFile::query()->sum('size'),
                'users' => UserFile::query()->distinct()->count('user_id'),
                'free' => @disk_free_space(storage_path('app')) ?: null,
            ],
            'people' => $this->people(trim((string) $request->query('users', ''))),
            'userSearch' => trim((string) $request->query('users', '')),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'quota_mb' => ['required', 'integer', 'min:1', 'max:1048576'],
            'max_mb' => ['required', 'integer', 'min:1', 'max:1048576'],
            'types' => ['required', 'in:all,media'],
        ]);
        $this->hosting->save(array_merge($data, ['enabled' => $request->boolean('enabled'), 'public' => $request->boolean('public')]));
        $this->alert->success('The file hosting was saved.')->flash();

        return redirect()->route('admin.files');
    }

    /**
     * The limits of one user, from the table of the users: empty = the default value.
     */
    public function limits(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'quota_mb' => ['nullable', 'integer', 'min:0', 'max:1048576'],
            'max_mb' => ['nullable', 'integer', 'min:1', 'max:1048576'],
        ]);
        // Only these columns: the rest of the user is not touched (nor validated again).
        User::query()->whereKey($user->id)->update([
            'file_quota' => blank($data['quota_mb'] ?? null) ? null : (int) $data['quota_mb'] * 1024 * 1024,
            'file_max' => blank($data['max_mb'] ?? null) ? null : (int) $data['max_mb'] * 1024 * 1024,
        ]);
        $this->alert->success('The limits of ' . $user->username . ' were saved.')->flash();

        return redirect()->back();
    }

    /**
     * Every user with the space they use and their own limits, the ones using the most first.
     */
    private function people(string $search)
    {
        $usage = UserFile::query()->selectRaw('user_id, SUM(size) as used, COUNT(*) as files')->groupBy('user_id');

        return User::query()
            ->leftJoinSub($usage, 'fh_usage', 'fh_usage.user_id', '=', 'users.id')
            ->select(['users.id', 'users.username', 'users.email', 'users.file_quota', 'users.file_max'])
            ->addSelect(DB::raw('COALESCE(fh_usage.used, 0) as used'), DB::raw('COALESCE(fh_usage.files, 0) as files'))
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q->where('users.username', 'like', '%' . $search . '%')->orWhere('users.email', 'like', '%' . $search . '%')))
            ->orderByDesc('used')->orderBy('users.username')
            ->paginate(15, ['*'], 'users_page')->withQueryString();
    }

    public function quota(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user' => ['required', 'string', 'max:191'],
            'quota_mb' => ['nullable', 'integer', 'min:0', 'max:1048576'],
            'max_mb' => ['nullable', 'integer', 'min:1', 'max:1048576'],
        ]);
        $user = User::query()->where('email', $data['user'])->orWhere('username', $data['user'])->first();
        if (!$user) {
            $this->alert->danger('No user has this email or username.')->flash();

            return redirect()->route('admin.files');
        }
        // Only this column: the rest of the user is not touched (nor validated again).
        User::query()->whereKey($user->id)->update([
            'file_quota' => blank($data['quota_mb'] ?? null) ? null : (int) $data['quota_mb'] * 1024 * 1024,
            'file_max' => blank($data['max_mb'] ?? null) ? null : (int) $data['max_mb'] * 1024 * 1024,
        ]);
        $this->alert->success('The space of the user was saved.')->flash();

        return redirect()->route('admin.files');
    }

    public function delete(UserFile $file): RedirectResponse
    {
        $this->hosting->delete($file);
        $this->alert->success('The file was removed.')->flash();

        return redirect()->back();
    }
}
