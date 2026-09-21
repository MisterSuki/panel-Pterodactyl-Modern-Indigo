<?php

namespace Pterodactyl\Services\Presence;

/**
 * Turns the address of a page of the dashboard into "where the person is": a short code from a fixed list, and the
 * identifier of the server if it is a page of a server. Only that leaves the browser and is kept, never the address
 * itself, so nothing typed in a path (a file name, for instance) ends up in the list of the administration.
 */
class PageResolver
{
    /**
     * The tabs of a server that are told apart. Any other one is just "server".
     */
    private const SERVER_TABS = ['files', 'databases', 'schedules', 'users', 'backups', 'network', 'startup', 'settings', 'activity'];

    /**
     * @return array{page: string, server: string|null}
     */
    public function resolve(string $path): array
    {
        $segments = array_values(array_filter(explode('/', trim($path)), fn (string $segment) => $segment !== ''));
        $first = $segments[0] ?? '';

        if ($first === '') {
            return ['page' => 'dashboard', 'server' => null];
        }
        if ($first === 'account') {
            return ['page' => 'account', 'server' => null];
        }
        if ($first === 'tickets') {
            return ['page' => 'tickets', 'server' => null];
        }
        if ($first === 'server' && isset($segments[1]) && preg_match('/^[A-Za-z0-9-]{1,36}$/', $segments[1]) === 1) {
            $tab = $segments[2] ?? '';

            return [
                'page' => $tab === '' ? 'console' : (in_array($tab, self::SERVER_TABS, true) ? $tab : 'server'),
                'server' => $segments[1],
            ];
        }

        return ['page' => 'panel', 'server' => null];
    }
}
