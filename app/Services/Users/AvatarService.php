<?php

namespace Pterodactyl\Services\Users;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Models\User;

/**
 * Keeps the profile pictures. A picture is a png, a jpeg or a webp file that is checked by what it really is (not by
 * its name), cut to a square and brought down to a small size when the server can do it, which also drops everything
 * else that was hidden in the file. It is kept outside of the public folder and served by the panel to people who are
 * signed in.
 */
class AvatarService
{
    public const DISK = 'local';

    public const DIRECTORY = 'avatars';

    public const SIZE = 256;

    public const MAX_BYTES = 2 * 1024 * 1024;

    public const MAX_SIDE = 6000;

    public const MIME = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'webp' => 'image/webp'];

    private const TYPES = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp'];

    /**
     * Replaces the picture of the person by the file that was sent.
     *
     * @throws DisplayException
     */
    public function store(User $user, UploadedFile $file): void
    {
        if (!$file->isValid() || $file->getSize() > self::MAX_BYTES) {
            throw new DisplayException('The picture is too big (2 MB at most).');
        }

        $raw = (string) file_get_contents($file->getRealPath());
        $info = @getimagesizefromstring($raw);
        if ($info === false || !isset(self::TYPES[$info[2]])) {
            throw new DisplayException('The file is not a picture (png, jpeg or webp).');
        }
        if ($info[0] < 16 || $info[1] < 16 || $info[0] > self::MAX_SIDE || $info[1] > self::MAX_SIDE) {
            throw new DisplayException('The picture is too small or too big.');
        }

        [$content, $extension] = $this->normalise($raw, $info);

        $this->forget($user);
        $name = $user->uuid . '.' . $extension;
        Storage::disk(self::DISK)->put(self::DIRECTORY . '/' . $name, $content);

        $user->forceFill(['avatar' => $name, 'avatar_updated_at' => now()])->saveQuietly();
    }

    /**
     * Goes back to the picture that everybody has by default.
     */
    public function remove(User $user): void
    {
        $this->forget($user);
        $user->forceFill(['avatar' => null, 'avatar_updated_at' => null])->saveQuietly();
    }

    /**
     * The path of the file of a person, or null when they kept the default picture.
     */
    public function path(User $user): ?string
    {
        if (!$user->avatar || preg_match('/^[A-Za-z0-9-]+\.(png|jpg|webp)$/', $user->avatar) !== 1) {
            return null;
        }
        $path = self::DIRECTORY . '/' . $user->avatar;

        return Storage::disk(self::DISK)->exists($path) ? Storage::disk(self::DISK)->path($path) : null;
    }

    private function forget(User $user): void
    {
        foreach (array_keys(self::MIME) as $extension) {
            Storage::disk(self::DISK)->delete(self::DIRECTORY . '/' . $user->uuid . '.' . $extension);
        }
    }

    /**
     * @param array<int, mixed> $info
     *
     * @return array{0: string, 1: string}
     */
    private function normalise(string $raw, array $info): array
    {
        $extension = self::TYPES[$info[2]];
        if (!function_exists('imagecreatefromstring') || !function_exists('imagepng')) {
            return [$raw, $extension];
        }

        $source = @imagecreatefromstring($raw);
        if ($source === false) {
            throw new DisplayException('The picture could not be read.');
        }

        $side = min($info[0], $info[1]);
        $target = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagefill($target, 0, 0, (int) imagecolorallocatealpha($target, 0, 0, 0, 127));
        imagecopyresampled($target, $source, 0, 0, intdiv($info[0] - $side, 2), intdiv($info[1] - $side, 2), self::SIZE, self::SIZE, $side, $side);

        ob_start();
        imagepng($target);
        $content = (string) ob_get_clean();

        return [$content, 'png'];
    }
}
