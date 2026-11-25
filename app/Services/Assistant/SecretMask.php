<?php

namespace Pterodactyl\Services\Assistant;

/**
 * Hides what looks like a key, a token or a password before a text goes to the AI. "(hidden)" is put in its place:
 * a change of a file that would contain it is refused, so a hidden value is never written over.
 */
class SecretMask
{
    public const HIDDEN = '(hidden)';

    public static function hide(string $text): string
    {
        $text = (string) preg_replace('/\b(cfxk_[A-Za-z0-9_]{10,}|gsk_[A-Za-z0-9]{20,}|ptl[ac]_[A-Za-z0-9]{20,}|sk-[A-Za-z0-9_-]{20,}|gh[pousr]_[A-Za-z0-9]{20,}|xox[abprs]-[A-Za-z0-9-]{10,})/', self::HIDDEN, $text);
        $text = (string) preg_replace('#(https://(?:discord(?:app)?\.com)/api/webhooks/)[^\s"\']+#i', '$1' . self::HIDDEN, $text);
        // "password=...", "token: ...", "sv_licenseKey ...", "rcon_password ...".
        $text = (string) preg_replace('/((?:password|passwd|pwd|token|secret|api[._-]?key|license[._-]?key|licenseKey|rcon[._-]?password)\s*[=:]\s*)("?)[^\s"\']+\2/i', '$1' . self::HIDDEN, $text);

        return (string) preg_replace('/^(\s*(?:set\s+|setr\s+)?(?:sv_licenseKey|steam_webApiKey|rcon_password|mysql_connection_string)\s+)("?)[^\s"]+\2/mi', '$1' . self::HIDDEN, $text);
    }
}
