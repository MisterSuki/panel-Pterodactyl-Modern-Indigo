<?php

namespace Pterodactyl\Services\Admin;

use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;
use Pterodactyl\Models\User;
use Pterodactyl\Services\Discord\DiscordBot;
use Pterodactyl\Services\Notifications\AlertTexts;
use Pterodactyl\Services\Notifications\Notifier;

/**
 * Tells the full administrators, once per version, that a new version of the theme is out: in the bell of the panel,
 * and in a private message from the bot when their Discord is linked. The update itself is one button away.
 */
class UpdateNotifier
{
    private const SETTING = 'theme:update_notified';

    public function __construct(
        private ThemeVersionService $versions,
        private SettingsRepositoryInterface $settings,
        private Notifier $notifier,
    ) {
    }

    /**
     * Looks for a new version and tells the administrators about it. Returns the version they were told about, or null.
     */
    public function check(): ?string
    {
        $installed = $this->versions->version();
        $latest = $this->versions->latestVersion();
        if ($installed === null || $latest === null || !version_compare($latest, $installed, '>')) {
            return null;
        }
        if ($this->settings->get(self::SETTING, '') === $latest) {
            return null;
        }
        $this->settings->set(self::SETTING, $latest);

        $vars = ['version' => $latest, 'installed' => $installed];
        $url = rtrim((string) config('app.url'), '/') . '/admin';
        $bot = app(DiscordBot::class);
        foreach (User::query()->where('root_admin', true)->get() as $admin) {
            $this->notifier->textToUser($admin, 'theme_update', $vars, 'update', '/admin');

            if ($admin->discord_id && $bot->configured()) {
                try {
                    [$title, $body] = AlertTexts::get('theme_update', $admin->language, $vars);
                    [$open] = AlertTexts::get('open_admin', $admin->language);
                    $bot->directMessage((string) $admin->discord_id, DiscordBot::card($title, [$body], 'update', array_values(array_filter([DiscordBot::link($open, $url)]))));
                } catch (\Throwable) {
                    // The bell is enough.
                }
            }
        }

        return $latest;
    }
}
