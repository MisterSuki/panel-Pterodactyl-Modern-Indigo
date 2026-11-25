<?php

namespace Pterodactyl\Repositories\Wings;

use Webmozart\Assert\Assert;
use Pterodactyl\Models\Server;
use GuzzleHttp\Exception\TransferException;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

/**
 * The last lines of the console of a server, as Wings keeps them (for the Claude connector).
 */
class DaemonConsoleRepository extends DaemonRepository
{
    /**
     * @return array<int, string>
     *
     * @throws DaemonConnectionException
     */
    public function lines(int $size = 100): array
    {
        Assert::isInstanceOf($this->server, Server::class);

        try {
            $response = $this->getHttpClient()->get(
                sprintf('/api/servers/%s/logs', $this->server->uuid),
                ['query' => ['size' => max(1, min(500, $size))]]
            );
        } catch (TransferException $exception) {
            throw new DaemonConnectionException($exception);
        }

        $data = json_decode($response->getBody()->__toString(), true);

        return array_values(array_map('strval', (array) ($data['data'] ?? [])));
    }
}
