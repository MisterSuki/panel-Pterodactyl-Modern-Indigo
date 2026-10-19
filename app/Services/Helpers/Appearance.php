<?php

namespace Pterodactyl\Services\Helpers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Models\User;

/**
 * The look of the panel. The administration picks the background everybody gets (the Liquid Glass gradient, an
 * animated effect or its own picture) and the default accent colour; each person can then pick their own accent colour,
 * and their own background when the administration allows it. The drawing itself is done by public/js/appearance.js.
 */
class Appearance
{
    public const ACCENTS = ['blue', 'indigo', 'purple', 'pink', 'red', 'orange', 'green', 'teal', 'graphite'];

    public const EFFECTS = ['aurora', 'silk', 'plasma', 'waves', 'lines', 'glow', 'beams', 'stars', 'bubbles'];

    public const GRADIENTS = ['liquid', 'midnight', 'ocean', 'sunset', 'forest'];

    public const MODES = ['gradient', 'animated', 'image'];

    public const DISK = 'local';

    public const DIRECTORY = 'appearance';

    public const MAX_BYTES = 8 * 1024 * 1024;

    public const MIME = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'webp' => 'image/webp', 'gif' => 'image/gif'];

    private const TYPES = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];

    private const KEY = 'appearance:site';

    private const SITE_DEFAULTS = [
        'accent' => 'blue',
        'mode' => 'gradient',
        'gradient' => 'liquid',
        'effect' => 'aurora',
        'dim' => 25,
        'blur' => 0,
        'user_choice' => true,
        'image' => null,
        'image_version' => 0,
    ];

    private const USER_DEFAULTS = [
        'accent' => 'default',
        'mode' => 'default',
        'gradient' => 'liquid',
        'effect' => 'aurora',
        'motion' => true,
    ];

    public function __construct(private SettingsRepositoryInterface $settings)
    {
    }

    /**
     * What the administration chose, as it is kept.
     */
    public function stored(): array
    {
        $raw = json_decode((string) $this->settings->get(self::KEY, ''), true);

        return $this->cleanSite(is_array($raw) ? $raw : []);
    }

    /**
     * What the administration chose, as the pages need it.
     */
    public function site(): array
    {
        $site = $this->stored();
        $image = $site['image'] && $this->imagePath() ? '/appearance/background?v=' . $site['image_version'] : null;

        return [
            'accent' => $site['accent'],
            'mode' => $site['mode'] === 'image' && !$image ? 'gradient' : $site['mode'],
            'gradient' => $site['gradient'],
            'effect' => $site['effect'],
            'dim' => $site['dim'],
            'blur' => $site['blur'],
            'userChoice' => $site['user_choice'],
            'image' => $image,
        ];
    }

    public function saveSite(array $values): void
    {
        $site = $this->cleanSite(array_merge($this->stored(), $values));
        $this->settings->set(self::KEY, json_encode($site));
    }

    /**
     * What a person chose for themselves.
     */
    public function forUser(?User $user): ?array
    {
        if (!$user) {
            return null;
        }
        $raw = json_decode((string) ($user->getAttributes()['appearance'] ?? ''), true);

        return $this->cleanUser(is_array($raw) ? $raw : []);
    }

    public function saveUser(User $user, array $values): array
    {
        $clean = $this->cleanUser(array_merge($this->forUser($user) ?? [], $values));
        User::query()->whereKey($user->id)->update(['appearance' => json_encode($clean)]);

        return $clean;
    }

    /**
     * Replaces the background picture. It is checked by what it really is (not by its name) and kept outside the
     * public folder; the panel serves it to everybody, the sign-in page included.
     *
     * @throws DisplayException
     */
    public function storeImage(UploadedFile $file): void
    {
        if (!$file->isValid() || $file->getSize() > self::MAX_BYTES) {
            throw new DisplayException('The picture is too big (8 MB at most).');
        }
        $raw = (string) file_get_contents($file->getRealPath());
        $info = @getimagesizefromstring($raw);
        if ($info === false || !isset(self::TYPES[$info[2]])) {
            throw new DisplayException('The file is not a picture (png, jpeg, webp or gif).');
        }
        if ($info[0] < 64 || $info[1] < 64 || $info[0] > 10000 || $info[1] > 10000) {
            throw new DisplayException('The picture is too small or too big.');
        }

        $this->forgetImage();
        $name = 'background.' . self::TYPES[$info[2]];
        Storage::disk(self::DISK)->put(self::DIRECTORY . '/' . $name, $raw);

        $site = $this->stored();
        $site['image'] = $name;
        $site['image_version'] = time();
        $site['mode'] = 'image';
        $this->settings->set(self::KEY, json_encode($this->cleanSite($site)));
    }

    public function removeImage(): void
    {
        $this->forgetImage();
        $site = $this->stored();
        $site['image'] = null;
        if ($site['mode'] === 'image') {
            $site['mode'] = 'gradient';
        }
        $this->settings->set(self::KEY, json_encode($this->cleanSite($site)));
    }

    /**
     * The file of the background picture, or null when there is none.
     */
    public function imagePath(): ?string
    {
        $name = $this->stored()['image'];
        if (!$name || preg_match('/^background\.(png|jpg|webp|gif)$/', $name) !== 1) {
            return null;
        }
        $path = self::DIRECTORY . '/' . $name;

        return Storage::disk(self::DISK)->exists($path) ? Storage::disk(self::DISK)->path($path) : null;
    }

    private function forgetImage(): void
    {
        foreach (array_keys(self::MIME) as $extension) {
            Storage::disk(self::DISK)->delete(self::DIRECTORY . '/background.' . $extension);
        }
    }

    private function cleanSite(array $raw): array
    {
        $site = array_merge(self::SITE_DEFAULTS, array_intersect_key($raw, self::SITE_DEFAULTS));

        return [
            'accent' => in_array($site['accent'], self::ACCENTS, true) ? $site['accent'] : 'blue',
            'mode' => in_array($site['mode'], self::MODES, true) ? $site['mode'] : 'gradient',
            'gradient' => in_array($site['gradient'], self::GRADIENTS, true) ? $site['gradient'] : 'liquid',
            'effect' => in_array($site['effect'], self::EFFECTS, true) ? $site['effect'] : 'aurora',
            'dim' => max(0, min(80, (int) $site['dim'])),
            'blur' => max(0, min(30, (int) $site['blur'])),
            'user_choice' => filter_var($site['user_choice'], FILTER_VALIDATE_BOOLEAN),
            'image' => is_string($site['image']) && $site['image'] !== '' ? $site['image'] : null,
            'image_version' => max(0, (int) $site['image_version']),
        ];
    }

    private function cleanUser(array $raw): array
    {
        $user = array_merge(self::USER_DEFAULTS, array_intersect_key($raw, self::USER_DEFAULTS));

        return [
            'accent' => $user['accent'] === 'default' || in_array($user['accent'], self::ACCENTS, true) ? $user['accent'] : 'default',
            'mode' => $user['mode'] === 'default' || in_array($user['mode'], self::MODES, true) ? $user['mode'] : 'default',
            'gradient' => in_array($user['gradient'], self::GRADIENTS, true) ? $user['gradient'] : 'liquid',
            'effect' => in_array($user['effect'], self::EFFECTS, true) ? $user['effect'] : 'aurora',
            'motion' => filter_var($user['motion'], FILTER_VALIDATE_BOOLEAN),
        ];
    }
}
