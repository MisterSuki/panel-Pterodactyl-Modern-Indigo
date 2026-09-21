<?php

namespace Pterodactyl\Services\Landing;

use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;

/**
 * The home page that visitors see before they sign in, and what the administration can change on it. The texts that
 * were not changed come from the language files, so the page is never empty; what was changed is kept in the settings of
 * the panel. Everything is written to the page as text (never as HTML), and a link can only be a path of the panel or an
 * address that starts with http:// or https://.
 */
class LandingContent
{
    /**
     * The icons a block of the page can have (drawn in the view).
     */
    public const ICONS = ['server', 'bolt', 'shield', 'headset', 'globe', 'database', 'star', 'users', 'gamepad', 'cloud'];

    public const MAX_FEATURES = 6;

    /**
     * The longest each text can be.
     */
    private const LIMITS = [
        'title' => 120, 'subtitle' => 300, 'cta_primary_text' => 40, 'cta_secondary_text' => 40,
        'features_title' => 80, 'about_title' => 80, 'about_text' => 2000, 'offers_title' => 80, 'footer_text' => 200,
    ];

    /**
     * The fields that are texts, in the order of the page.
     */
    public const TEXTS = ['title', 'subtitle', 'cta_primary_text', 'cta_secondary_text', 'features_title', 'about_title', 'about_text', 'offers_title', 'footer_text'];

    public const LINKS = ['cta_primary_link', 'cta_secondary_link'];

    public function __construct(private SettingsRepositoryInterface $settings)
    {
    }

    /**
     * Whether visitors see this page. When it is off they are sent to the sign-in page, as before.
     */
    public function enabled(): bool
    {
        return $this->settings->get('home:enabled', '1') !== '0';
    }

    public function setEnabled(bool $enabled): void
    {
        $this->settings->set('home:enabled', $enabled ? '1' : '0');
    }

    public function showOffers(): bool
    {
        return (bool) ($this->saved()['show_offers'] ?? true);
    }

    /**
     * Everything the page shows: what the administration wrote, or the default.
     *
     * @return array<string, mixed>
     */
    public function get(?string $locale = null): array
    {
        $saved = $this->saved();
        $defaults = $this->defaults($locale);

        $content = [];
        foreach (self::TEXTS as $key) {
            $content[$key] = array_key_exists($key, $saved) ? (string) $saved[$key] : $defaults[$key];
        }
        foreach (self::LINKS as $key) {
            $content[$key] = array_key_exists($key, $saved) ? (string) $saved[$key] : $defaults[$key];
        }
        $content['features'] = array_key_exists('features', $saved) ? $saved['features'] : $defaults['features'];
        $content['show_offers'] = (bool) ($saved['show_offers'] ?? true);

        return $content;
    }

    /**
     * Keeps what the administration sent, after cleaning it. Nothing that is not a text, a link or an icon of the list
     * gets in.
     *
     * @param array<string, mixed> $input
     */
    public function save(array $input): void
    {
        $saved = [];
        foreach (self::TEXTS as $key) {
            $saved[$key] = mb_substr(trim(str_replace("\r\n", "\n", (string) ($input[$key] ?? ''))), 0, self::LIMITS[$key]);
        }
        foreach (self::LINKS as $key) {
            $saved[$key] = $this->link((string) ($input[$key] ?? ''));
        }

        $features = [];
        foreach (array_slice(array_values((array) ($input['features'] ?? [])), 0, self::MAX_FEATURES) as $feature) {
            $title = mb_substr(trim((string) ($feature['title'] ?? '')), 0, 60);
            if ($title === '') {
                continue;
            }
            $icon = (string) ($feature['icon'] ?? '');
            $features[] = [
                'icon' => in_array($icon, self::ICONS, true) ? $icon : 'star',
                'title' => $title,
                'text' => mb_substr(trim((string) ($feature['text'] ?? '')), 0, 240),
            ];
        }
        $saved['features'] = $features;
        $saved['show_offers'] = (bool) ($input['show_offers'] ?? false);

        $this->settings->set('home:content', json_encode($saved, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Goes back to the texts of the language files.
     */
    public function reset(): void
    {
        $this->settings->forget('home:content');
    }

    /**
     * A link that can be trusted: empty (no button), a path of the panel, or an address with http or https. Anything
     * else (javascript:, data:, //other-site...) is dropped.
     */
    public function link(string $link): string
    {
        $link = trim($link);
        if ($link === '') {
            return '';
        }
        if (preg_match('#^/(?!/)[^\s\\\\]*$#', $link) === 1 || preg_match('#^https?://[^\s<>"\'\\\\]+$#i', $link) === 1) {
            return mb_substr($link, 0, 300);
        }

        return '';
    }

    /**
     * @return array<string, mixed>
     */
    private function saved(): array
    {
        $raw = $this->settings->get('home:content', null);
        $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(?string $locale): array
    {
        $t = fn (string $key) => (string) trans('landing.' . $key, [], $locale);

        return [
            'title' => $t('title'),
            'subtitle' => $t('subtitle'),
            'cta_primary_text' => $t('cta_primary_text'),
            'cta_primary_link' => '/auth/login',
            'cta_secondary_text' => $t('cta_secondary_text'),
            'cta_secondary_link' => '/auth/register',
            'features_title' => $t('features_title'),
            'about_title' => '',
            'about_text' => '',
            'offers_title' => $t('offers_title'),
            'footer_text' => '',
            'features' => array_map(fn (array $feature) => [
                'icon' => $feature['icon'],
                'title' => (string) trans('landing.features.' . $feature['key'] . '.title', [], $locale),
                'text' => (string) trans('landing.features.' . $feature['key'] . '.text', [], $locale),
            ], [
                ['icon' => 'bolt', 'key' => 'speed'],
                ['icon' => 'server', 'key' => 'console'],
                ['icon' => 'shield', 'key' => 'backups'],
                ['icon' => 'headset', 'key' => 'support'],
            ]),
        ];
    }
}
