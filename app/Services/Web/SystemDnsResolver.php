<?php

namespace Pterodactyl\Services\Web;

/**
 * Asks the DNS of the machine that the panel runs on.
 */
class SystemDnsResolver implements DnsResolver
{
    public function addresses(string $host): array
    {
        $addresses = [];

        $v4 = @gethostbynamel($host);
        if (is_array($v4)) {
            $addresses = $v4;
        }

        $v6 = @dns_get_record($host, DNS_AAAA);
        if (is_array($v6)) {
            foreach ($v6 as $record) {
                if (!empty($record['ipv6'])) {
                    $addresses[] = $record['ipv6'];
                }
            }
        }

        return array_values(array_unique($addresses));
    }
}
