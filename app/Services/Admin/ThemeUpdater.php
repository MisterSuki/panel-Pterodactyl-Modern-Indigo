<?php

namespace Pterodactyl\Services\Admin;

/**
 * Updates the theme from the admin area, with one button.
 *
 * The web server never runs the update itself (it would need root). The installer sets up a small systemd service on
 * the panel server: when the panel drops the file storage/app/theme-update.request, systemd starts the service as
 * root, which deletes the file and runs the official "install.sh --update --yes" of this repository. The content of
 * the file is never read, so nothing the web server writes can change what is run. The service writes its progress
 * and its log in /var/lib/pterodactyl-theme-update, a folder the web server can read but not write.
 */
class ThemeUpdater
{
    public const STATE_DIR = '/var/lib/pterodactyl-theme-update';

    private const LOG_LINES = 80;

    /**
     * Whether the service is installed on this server (it is from the first "--update" that ships it).
     */
    public function available(): bool
    {
        return @is_file(self::STATE_DIR . '/enabled');
    }

    public function requestPath(): string
    {
        return storage_path('app/theme-update.request');
    }

    /**
     * Asks the service to update the theme. Returns false when an update is already asked or running.
     */
    public function request(): bool
    {
        $status = $this->status();
        if ($status['pending'] || $status['state'] === 'running') {
            return false;
        }

        return @file_put_contents($this->requestPath(), (string) time()) !== false;
    }

    /**
     * Where the update is: idle, pending (asked, not started yet), running, done or failed; with the end of its log.
     */
    public function status(): array
    {
        $raw = @file_get_contents(self::STATE_DIR . '/status.json');
        $state = is_string($raw) ? json_decode($raw, true) : null;
        $state = is_array($state) ? $state : [];
        $current = in_array($state['state'] ?? null, ['running', 'done', 'failed'], true) ? $state['state'] : 'idle';
        // A "running" left over by a server that restarted in the middle never ends: after two hours it counts as failed.
        if ($current === 'running' && isset($state['started_at']) && (int) $state['started_at'] < time() - 7200) {
            $current = 'failed';
        }

        return [
            'available' => $this->available(),
            'pending' => @is_file($this->requestPath()),
            'state' => $current,
            'code' => isset($state['code']) ? (int) $state['code'] : null,
            'startedAt' => isset($state['started_at']) ? (int) $state['started_at'] : null,
            'finishedAt' => isset($state['finished_at']) ? (int) $state['finished_at'] : null,
            'log' => $this->log(),
        ];
    }

    /**
     * The last lines of the log of the update, without the terminal colours.
     */
    private function log(): string
    {
        // Only the end of the file: the output of Yarn can make it several megabytes.
        $path = self::STATE_DIR . '/update.log';
        $size = @filesize($path);
        if (!$size) {
            return '';
        }
        $raw = @file_get_contents($path, false, null, max(0, $size - 32768));
        if (!is_string($raw) || $raw === '') {
            return '';
        }
        $raw = preg_replace('/\x1b\[[0-9;?]*[A-Za-z]/', '', $raw) ?? $raw;
        $raw = str_replace("\r", "\n", $raw);
        $lines = array_values(array_filter(explode("\n", $raw), fn ($line) => trim($line) !== ''));

        return implode("\n", array_slice($lines, -self::LOG_LINES));
    }
}
