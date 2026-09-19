<?php

namespace Pterodactyl\Services\Helpers;

/**
 * The languages the panel is available in: one folder per language in resources/lang.
 */
class Locales
{
    /**
     * The language codes that can be chosen, English first.
     *
     * @return string[]
     */
    public static function codes(): array
    {
        $codes = array_map('basename', glob(resource_path('lang') . '/*', GLOB_ONLYDIR) ?: []);
        sort($codes);

        return array_values(array_unique(array_merge(['en'], $codes)));
    }

    public static function isAvailable(?string $code): bool
    {
        return is_string($code) && in_array($code, self::codes(), true);
    }

    /**
     * The language the panel uses for visitors and new accounts. English unless another one was chosen.
     */
    public static function default(): string
    {
        $configured = config('app.locale', 'en');

        return self::isAvailable($configured) ? $configured : 'en';
    }
}
