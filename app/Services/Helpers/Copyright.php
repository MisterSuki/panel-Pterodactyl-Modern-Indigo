<?php

namespace Pterodactyl\Services\Helpers;

/**
 * The copyright line at the bottom of the pages. The panel's own line is shown until an administrator writes another
 * one in Admin > Settings.
 */
class Copyright
{
    /**
     * The custom line, or null while the default one is used. "{year}" stands for the current year.
     *
     * @return array{text: string, url: string|null}|null
     */
    public static function custom(): ?array
    {
        $text = trim((string) config('pterodactyl.copyright.text', ''));
        if ($text === '') {
            return null;
        }

        $url = trim((string) config('pterodactyl.copyright.url', ''));

        return [
            'text' => str_replace('{year}', date('Y'), $text),
            'url' => preg_match('#^https?://#i', $url) === 1 ? $url : null,
        ];
    }
}
