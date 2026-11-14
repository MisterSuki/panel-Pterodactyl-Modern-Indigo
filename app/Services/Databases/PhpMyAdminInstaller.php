<?php

namespace Pterodactyl\Services\Databases;

use Pterodactyl\Services\Admin\ThemeVersionService;
use Pterodactyl\Services\Nodes\WingsInstaller;
use Pterodactyl\Services\Remote\SshTask;

/**
 * Installs phpMyAdmin from the panel: connects with SSH to the machine of the panel and runs the installer of the theme
 * with --phpmyadmin (download, sign-in script, nginx include checked by nginx -t, PHPMYADMIN_URL in the .env).
 */
class PhpMyAdminInstaller extends SshTask
{
    protected const LIMIT = 900;

    public function __construct(WingsInstaller $keys, private ThemeVersionService $versions)
    {
        parent::__construct($keys);
    }

    protected function slot(): string
    {
        return 'phpmyadmin';
    }

    protected function command(): string
    {
        return 'p:phpmyadmin:install';
    }

    protected function script(array $request): string
    {
        $installer = str_replace(' --update', '', $this->versions->updateCommand());

        return implode("\n", [
            'export DEBIAN_FRONTEND=noninteractive',
            'command -v curl >/dev/null 2>&1 || { apt-get update -qq && apt-get install -y -qq curl; }',
            $installer . ' --phpmyadmin --yes --path=' . $this->quote((string) $request['path']),
        ]);
    }

    protected function finish(array $request, callable $log): ?string
    {
        $log("\n✓ phpMyAdmin is installed. Reload the page: the database buttons open it.\n");

        return null;
    }

    protected function explainLog(string $end): ?string
    {
        if (str_contains($end, 'no pterodactyl panel found')) {
            return 'This machine is not the one of the panel: phpMyAdmin is installed next to the panel. Use the address of the machine of the panel.';
        }
        if (str_contains($end, 'nginx was not found')) {
            return 'phpMyAdmin is only set up with nginx, which was not found on this machine.';
        }

        return parent::explainLog($end);
    }
}
