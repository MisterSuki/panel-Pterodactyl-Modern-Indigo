<?php

namespace Pterodactyl\Http\Controllers\Base;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\UserFile;
use Pterodactyl\Models\UserFolderShare;
use Pterodactyl\Services\Files\FileHosting;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The page of a shared folder (/d/<token>): its files and its folders, for anyone with the link. "?p=" opens one of
 * its folders; nothing outside the shared folder can be reached.
 */
class UserFolderController extends Controller
{
    public function __construct(private FileHosting $hosting)
    {
    }

    public function show(Request $request, string $token): View
    {
        /** @var UserFolderShare|null $share */
        $share = UserFolderShare::query()->where('token', $token)->first();
        if (!$share || !$this->hosting->enabled() || !$this->hosting->config()['public']) {
            throw new NotFoundHttpException();
        }

        // The folder opened, relative to the shared one ("" for the shared folder itself).
        $sub = $this->hosting->cleanFolder((string) $request->query('p', ''));
        $current = $sub === '' ? $share->folder : $share->folder . '/' . $sub;

        $all = UserFile::query()->where('user_id', $share->user_id)
            ->where(fn ($q) => $q->where('folder', $share->folder)->orWhere('folder', 'like', addcslashes($share->folder, '%_\\') . '/%'))
            ->orderBy('name')->get();

        $inside = $all->filter(fn (UserFile $file) => $this->hosting->within((string) $file->folder, $current));
        if ($sub !== '' && $inside->isEmpty()) {
            throw new NotFoundHttpException();
        }

        if ($sub === '') {
            UserFolderShare::query()->whereKey($share->id)->increment('views');
        }

        // The folders right under the one opened, with what they hold.
        $folders = $inside->filter(fn (UserFile $file) => (string) $file->folder !== $current)
            ->groupBy(fn (UserFile $file) => explode('/', substr((string) $file->folder, strlen($current) + 1))[0])
            ->map(fn ($files, $name) => [
                'name' => (string) $name,
                'path' => ltrim($sub . '/' . $name, '/'),
                'count' => $files->count(),
                'size' => (int) $files->sum('size'),
            ])->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();

        $files = $inside->filter(fn (UserFile $file) => (string) $file->folder === $current)
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->map(fn (UserFile $file) => $this->hosting->present($file))->values();

        // The way back: the shared folder, then each folder opened in it.
        $crumbs = [['name' => basename($share->folder), 'path' => '']];
        $walked = '';
        foreach ($sub === '' ? [] : explode('/', $sub) as $part) {
            $walked = ltrim($walked . '/' . $part, '/');
            $crumbs[] = ['name' => $part, 'path' => $walked];
        }

        return view('files.folder', [
            'token' => $share->token,
            'owner' => $share->user->username,
            'crumbs' => $crumbs,
            'folders' => $folders,
            'files' => $files,
            'total' => ['count' => $all->count(), 'size' => (int) $all->sum('size')],
        ]);
    }
}
