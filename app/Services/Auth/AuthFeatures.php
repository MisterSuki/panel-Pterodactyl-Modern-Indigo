<?php

namespace Pterodactyl\Services\Auth;

/**
 * Reads the optional authentication features (self-registration and Discord login) from
 * the configuration. Values loaded from the settings table are strings, so every read goes
 * through a boolean filter instead of trusting the raw config value.
 */
class AuthFeatures
{
    public static function registrationEnabled(): bool
    {
        return self::toBool(config('pterodactyl.auth.registration'));
    }

    /**
     * Discord login is only offered when it is switched on and fully configured.
     */
    public static function discordEnabled(): bool
    {
        return self::toBool(config('pterodactyl.auth.discord.enabled'))
            && !empty(self::discordClientId())
            && !empty(self::discordClientSecret());
    }

    public static function discordClientId(): ?string
    {
        $value = config('pterodactyl.auth.discord.client_id');

        return empty($value) ? null : (string) $value;
    }

    public static function discordClientSecret(): ?string
    {
        $value = config('pterodactyl.auth.discord.client_secret');

        return empty($value) ? null : (string) $value;
    }

    private static function toBool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
