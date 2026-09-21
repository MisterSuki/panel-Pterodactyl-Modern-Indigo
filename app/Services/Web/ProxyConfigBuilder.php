<?php

namespace Pterodactyl\Services\Web;

/**
 * Writes the configuration of the web server (Caddy) that leads every domain to the server that runs its site, with the
 * HTTPS certificate of each name made and renewed by Caddy itself. Everything that goes into the file is checked first, so
 * that no name and no address can add a line of its own to it.
 */
class ProxyConfigBuilder
{
    /**
     * @param iterable<array{hosts: array<int, string>, target: string}> $entries
     */
    public function build(iterable $entries): string
    {
        $lines = [
            '# Made by the panel: do not edit, this file is replaced every time it changes.',
        ];

        foreach ($entries as $entry) {
            $hosts = array_values(array_filter($entry['hosts'], fn ($host) => is_string($host) && self::validHost($host)));
            if ($hosts === [] || !self::validTarget($entry['target'])) {
                continue;
            }

            $lines[] = '';
            $lines[] = implode(', ', $hosts) . ' {';
            $lines[] = "\tencode zstd gzip";
            $lines[] = "\treverse_proxy " . $entry['target'];
            $lines[] = '}';
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * A name made of letters, digits, hyphens and dots, and nothing else.
     */
    public static function validHost(string $host): bool
    {
        return strlen($host) <= 253 && preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z][a-z0-9-]{0,61}[a-z0-9]$/', $host) === 1;
    }

    /**
     * An address and a port: "203.0.113.5:8080", "[2001:db8::1]:8080" or "node.example.com:8080".
     */
    public static function validTarget(string $target): bool
    {
        if (preg_match('/^(?<host>\[[0-9a-f:.]+\]|[a-z0-9.-]+):(?<port>\d{1,5})$/i', $target, $match) !== 1) {
            return false;
        }
        $port = (int) $match['port'];

        return $port >= 1 && $port <= 65535 && !str_contains($match['host'], '..');
    }
}
