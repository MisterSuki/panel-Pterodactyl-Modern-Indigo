<?php

namespace Pterodactyl\Services\Files;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Models\User;
use Pterodactyl\Models\UserFile;

/**
 * The file hosting of the users: pictures, videos, sounds or any file kept on the panel, with a link to share them.
 * Off until the administration opens it. Files are sent in pieces (so a video can be bigger than the upload limit of
 * nginx and PHP), checked by what they really are, and only safe media are shown in the browser: everything else is
 * downloaded, so a file can never run anything on the domain of the panel.
 */
class FileHosting
{
    // The size of one piece of an upload.
    public const CHUNK = 8 * 1024 * 1024;

    private const MB = 1024 * 1024;

    // Shown in the browser (with a strict sandbox); every other file is downloaded.
    public const INLINE = [
        'image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/avif', 'image/bmp',
        'video/mp4', 'video/webm', 'video/ogg',
        'audio/mpeg', 'audio/ogg', 'audio/wav', 'audio/x-wav', 'audio/webm', 'audio/mp4', 'audio/aac', 'audio/flac', 'audio/x-flac',
    ];

    // The names a "media only" hosting accepts.
    private const MEDIA_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'bmp', 'mp4', 'webm', 'ogv', 'mov', 'mkv', 'mp3', 'ogg', 'oga', 'opus', 'wav', 'flac', 'm4a', 'aac'];

    public function __construct(private SettingsRepositoryInterface $settings)
    {
    }

    public function enabled(): bool
    {
        return $this->settings->get('files:enabled', '0') === '1';
    }

    /**
     * @return array{enabled: bool, quota_mb: int, types: string, public: bool}
     */
    public function config(): array
    {
        return [
            'enabled' => $this->enabled(),
            'quota_mb' => max(1, (int) $this->settings->get('files:quota_mb', '1024')),
            'types' => $this->settings->get('files:types', 'all') === 'media' ? 'media' : 'all',
            'public' => $this->settings->get('files:public', '1') === '1',
        ];
    }

    public function save(array $values): void
    {
        $this->settings->set('files:enabled', !empty($values['enabled']) ? '1' : '0');
        $this->settings->set('files:quota_mb', (string) max(1, min(1048576, (int) $values['quota_mb'])));
        $this->settings->set('files:types', ($values['types'] ?? 'all') === 'media' ? 'media' : 'all');
        $this->settings->set('files:public', !empty($values['public']) ? '1' : '0');
    }

    /**
     * The space of a user, in bytes: their own when the administration set one, the default one otherwise.
     */
    public function quota(User $user): int
    {
        $own = $user->getAttribute('file_quota');

        return $own !== null ? (int) $own : $this->config()['quota_mb'] * self::MB;
    }

    /**
     * The biggest file a user may send, in bytes: their own when the administration set one, the default one otherwise.
     */
    public function maxFile(User $user): int
    {
        $own = $user->getAttribute('file_max');

        // No limit for one file: only the space of the user counts (a big file that fills it is their choice).
        return $this->quota($user);
    }

    /**
     * A folder as it is kept: parts separated by "/", without "." or "..", each part cleaned, 10 levels at most.
     */
    public function cleanFolder(string $folder): string
    {
        $parts = [];
        foreach (explode('/', str_replace('\\', '/', $folder)) as $part) {
            $part = trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', $part));
            if ($part === '' || $part === '.' || $part === '..') {
                continue;
            }
            $parts[] = mb_substr($part, 0, 60);
        }

        return mb_substr(implode('/', array_slice($parts, 0, 10)), 0, 191);
    }

    /**
     * Moves files of a user into a folder ("" for the top).
     *
     * @param array<int, string> $uuids
     */
    public function move(User $user, array $uuids, string $folder): int
    {
        return UserFile::query()->where('user_id', $user->id)->whereIn('uuid', $uuids)->update(['folder' => $this->cleanFolder($folder)]);
    }

    public function used(User $user): int
    {
        return (int) UserFile::query()->where('user_id', $user->id)->sum('size');
    }

    /**
     * Starts an upload: checks the size, the space left and the name, and gives the id the pieces are sent to.
     *
     * @throws DisplayException
     */
    public function begin(User $user, string $name, int $size, string $folder = ''): string
    {
        $config = $this->config();
        if (!$config['enabled']) {
            throw new DisplayException('The file hosting is not open.');
        }
        $name = $this->cleanName($name);
        if ($size <= 0) {
            throw new DisplayException('This file is empty.');
        }
        if ($config['types'] === 'media' && !in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), self::MEDIA_EXTENSIONS, true)) {
            throw new DisplayException('Only pictures, videos and sounds can be sent here.');
        }
        if ($this->used($user) + $this->pending($user) + $size > $this->quota($user)) {
            throw new DisplayException('Not enough space left for this file.');
        }

        $id = Str::random(32);
        $this->directory('tmp');
        touch($this->tmpPath($id));
        Cache::put($this->key($id), ['user' => $user->id, 'name' => $name, 'folder' => $this->cleanFolder($folder), 'size' => $size, 'received' => 0, 'chunks' => 0], now()->addDay());
        $pending = Cache::get('file-upload-pending:' . $user->id, []);
        $pending[$id] = $size;
        Cache::put('file-upload-pending:' . $user->id, $pending, now()->addDay());

        return $id;
    }

    /**
     * Adds the next piece of an upload.
     *
     * @param resource $stream
     *
     * @throws DisplayException
     */
    public function chunk(User $user, string $id, int $index, $stream): void
    {
        $upload = $this->upload($user, $id);
        if ($index !== $upload['chunks']) {
            throw new DisplayException('The pieces of the file came in the wrong order. Send it again.');
        }

        $out = fopen($this->tmpPath($id), 'ab');
        if ($out === false) {
            throw new DisplayException('The file could not be written on the panel.');
        }
        $written = stream_copy_to_stream($stream, $out, self::CHUNK + 1);
        fclose($out);

        $received = $upload['received'] + (int) $written;
        if ($written === false || $written > self::CHUNK || $received > $upload['size']) {
            $this->cancel($user, $id);
            throw new DisplayException('The file is bigger than announced.');
        }
        $upload['received'] = $received;
        ++$upload['chunks'];
        Cache::put($this->key($id), $upload, now()->addDay());
    }

    /**
     * Finishes an upload: the file is checked by what it really is and kept.
     *
     * @throws DisplayException
     */
    public function complete(User $user, string $id): UserFile
    {
        $upload = $this->upload($user, $id);
        $tmp = $this->tmpPath($id);
        if ($upload['received'] !== $upload['size'] || filesize($tmp) !== $upload['size']) {
            $this->cancel($user, $id);
            throw new DisplayException('The file did not arrive whole. Send it again.');
        }

        $mime = $this->detect($tmp);
        if ($this->config()['types'] === 'media' && !preg_match('#^(image|video|audio)/#', $mime)) {
            $this->cancel($user, $id);
            throw new DisplayException('Only pictures, videos and sounds can be sent here.');
        }

        $uuid = (string) Str::uuid();
        $relative = 'user-files/' . $user->id . '/' . $uuid;
        $this->directory((string) $user->id);
        if (!@rename($tmp, storage_path('app/' . $relative))) {
            $this->cancel($user, $id);
            throw new DisplayException('The file could not be written on the panel.');
        }
        $this->forget($user, $id);

        /** @var UserFile $file */
        $file = UserFile::query()->create([
            'uuid' => $uuid,
            'user_id' => $user->id,
            'name' => $upload['name'],
            'folder' => (string) ($upload['folder'] ?? ''),
            'path' => $relative,
            'mime' => mb_substr($mime, 0, 100),
            'size' => $upload['size'],
            'token' => Str::random(24),
            'public' => $this->config()['public'],
        ]);

        return $file;
    }

    public function cancel(User $user, string $id): void
    {
        @unlink($this->tmpPath($id));
        $this->forget($user, $id);
    }

    public function delete(UserFile $file): void
    {
        @unlink($this->path($file));
        $file->delete();
    }

    public function path(UserFile $file): string
    {
        return storage_path('app/' . $file->path);
    }

    /**
     * Whether the browser may show the file itself (a safe picture, video or sound).
     */
    public function inline(UserFile $file): bool
    {
        return in_array($file->mime, self::INLINE, true);
    }

    public function present(UserFile $file): array
    {
        return [
            'uuid' => $file->uuid,
            'name' => $file->name,
            'folder' => (string) $file->folder,
            'mime' => $file->mime,
            'kind' => preg_match('#^(image|video|audio)/#', $file->mime, $m) ? $m[1] : 'other',
            'inline' => $this->inline($file),
            'size' => $file->size,
            'public' => $file->public,
            'views' => $file->views,
            'url' => rtrim((string) config('app.url'), '/') . '/f/' . $file->token,
            'created_at' => $file->created_at?->toAtomString(),
        ];
    }

    public function cleanName(string $name): string
    {
        $name = trim(str_replace(['/', '\\', "\0"], '-', $name));
        $name = (string) preg_replace('/[\x00-\x1F\x7F]/u', '', $name);

        return mb_substr($name !== '' ? $name : 'file', 0, 180);
    }

    private function detect(string $path): string
    {
        $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
        $mime = $finfo ? (string) finfo_file($finfo, $path) : 'application/octet-stream';

        return $mime !== '' ? $mime : 'application/octet-stream';
    }

    /**
     * @throws DisplayException
     */
    private function upload(User $user, string $id): array
    {
        $upload = preg_match('/^[A-Za-z0-9]{32}$/', $id) === 1 ? Cache::get($this->key($id)) : null;
        if (!is_array($upload) || $upload['user'] !== $user->id || !is_file($this->tmpPath($id))) {
            throw new DisplayException('This upload does not exist any more. Send the file again.');
        }

        return $upload;
    }

    private function pending(User $user): int
    {
        return (int) array_sum((array) Cache::get('file-upload-pending:' . $user->id, []));
    }

    private function forget(User $user, string $id): void
    {
        Cache::forget($this->key($id));
        $pending = (array) Cache::get('file-upload-pending:' . $user->id, []);
        unset($pending[$id]);
        Cache::put('file-upload-pending:' . $user->id, $pending, now()->addDay());
    }

    private function key(string $id): string
    {
        return 'file-upload:' . $id;
    }

    private function tmpPath(string $id): string
    {
        return storage_path('app/user-files/tmp/' . $id . '.part');
    }

    private function directory(string $name): string
    {
        $directory = storage_path('app/user-files/' . $name);
        if (!is_dir($directory)) {
            mkdir($directory, 0750, true);
        }

        return $directory;
    }
}
