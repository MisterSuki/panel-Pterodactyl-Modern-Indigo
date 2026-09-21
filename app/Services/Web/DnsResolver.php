<?php

namespace Pterodactyl\Services\Web;

/**
 * Where a name leads. Kept apart so the checks can be tested without the network.
 */
interface DnsResolver
{
    /**
     * The IP addresses (v4 and v6) that a name leads to, after following its aliases (CNAME).
     *
     * @return array<int, string>
     */
    public function addresses(string $host): array;
}
