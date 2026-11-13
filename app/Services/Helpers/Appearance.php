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

    public const LOGO_MAX_BYTES = 2 * 1024 * 1024;

    public const LOGO_MIME = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'webp' => 'image/webp', 'gif' => 'image/gif', 'svg' => 'image/svg+xml'];

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
        'logo' => null,
        'logo_version' => 0,
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

    /**
     * The address of the logo chosen by the administration, or null when there is none (the mark in the accent colour
     * is shown instead). The address changes with each new logo, so browsers may keep it for long.
     */
    public function logoUrl(): ?string
    {
        $site = $this->stored();

        return $site['logo'] && $this->logoPath() ? '/appearance/logo?v=' . $site['logo_version'] : null;
    }

    /**
     * Replaces the logo. A picture is checked by what it really is (not by its name); an SVG must be a plain drawing:
     * no script, no event handler, no link to anything outside. It is kept outside the public folder.
     *
     * @throws DisplayException
     */
    public function storeLogo(UploadedFile $file): void
    {
        if (!$file->isValid() || $file->getSize() > self::LOGO_MAX_BYTES) {
            throw new DisplayException('The logo is too big (2 MB at most).');
        }
        $raw = (string) file_get_contents($file->getRealPath());

        $info = @getimagesizefromstring($raw);
        if ($info !== false && isset(self::TYPES[$info[2]])) {
            if ($info[0] < 16 || $info[1] < 16 || $info[0] > 4096 || $info[1] > 4096) {
                throw new DisplayException('The logo is too small or too big (16 to 4096 pixels).');
            }
            $extension = self::TYPES[$info[2]];
        } elseif ($this->isSafeSvg($raw)) {
            $extension = 'svg';
        } else {
            throw new DisplayException('The file is not a logo (png, jpeg, webp, gif or svg).');
        }

        $this->forgetLogo();
        $name = 'logo.' . $extension;
        Storage::disk(self::DISK)->put(self::DIRECTORY . '/' . $name, $raw);

        $site = $this->stored();
        $site['logo'] = $name;
        $site['logo_version'] = time();
        $this->settings->set(self::KEY, json_encode($this->cleanSite($site)));
    }

    public function removeLogo(): void
    {
        $this->forgetLogo();
        $site = $this->stored();
        $site['logo'] = null;
        $this->settings->set(self::KEY, json_encode($this->cleanSite($site)));
    }

    /**
     * The file of the logo, or null when there is none.
     */
    public function logoPath(): ?string
    {
        $name = $this->stored()['logo'];
        if (!$name || preg_match('/^logo\.(png|jpg|webp|gif|svg)$/', $name) !== 1) {
            return null;
        }
        $path = self::DIRECTORY . '/' . $name;

        return Storage::disk(self::DISK)->exists($path) ? Storage::disk(self::DISK)->path($path) : null;
    }

    private function forgetLogo(): void
    {
        foreach (array_keys(self::LOGO_MIME) as $extension) {
            Storage::disk(self::DISK)->delete(self::DIRECTORY . '/logo.' . $extension);
        }
    }

    /**
     * An SVG with nothing in it that could run or load something: no script, no foreignObject, no on* attribute, no
     * javascript: or outside link. The file is also served with a policy that forbids scripts, in case.
     */
    private function isSafeSvg(string $raw): bool
    {
        if (strlen($raw) > self::LOGO_MAX_BYTES || preg_match('/<svg[\s>]/i', $raw) !== 1) {
            return false;
        }
        if (preg_match('/<!ENTITY|<script|<foreignObject|<iframe|<embed|<object|\son[a-z]+\s*=|javascript:|data:text\/html/i', $raw) === 1) {
            return false;
        }

        // Links only to something inside the drawing itself (#id).
        return preg_match('/(?:xlink:)?href\s*=\s*["\'](?!#)/i', $raw) !== 1;
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
            'logo' => is_string($site['logo']) && $site['logo'] !== '' ? $site['logo'] : null,
            'logo_version' => max(0, (int) $site['logo_version']),
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
