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
            'quotas' => User::query()->where(fn ($q) => $q->whereNotNull('file_quota')->orWhereNotNull('file_max'))
                ->orderBy('username')->limit(100)->get(['id', 'username', 'email', 'file_quota', 'file_max']),
            'top' => UserFile::query()->select('user_id', DB::raw('SUM(size) as total'), DB::raw('COUNT(*) as files'))
                ->groupBy('user_id')->orderByDesc('total')->limit(10)->with('user:id,username')->get(),
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
            'file_quota' => $data['quota_mb'] === null ? null : (int) $data['quota_mb'] * 1024 * 1024,
            'file_max' => ($data['max_mb'] ?? null) === null ? null : (int) $data['max_mb'] * 1024 * 1024,
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
