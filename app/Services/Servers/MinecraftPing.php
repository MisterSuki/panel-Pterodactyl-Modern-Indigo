<?php

namespace Pterodactyl\Services\Servers;

/**
 * Asks a Minecraft Java server how many players are on it (the "server list ping" of the game), like the server list
 * of the game does. One short TCP exchange with a small time limit.
 */
class MinecraftPing
{
    /**
     * @return array{players: int, max_players: int|null}|null
     */
    public function ask(string $host, int $port, float $timeout = 1.5): ?array
    {
        $socket = @fsockopen($host, $port, $errno, $error, $timeout);
        if (!$socket) {
            return null;
        }
        stream_set_timeout($socket, (int) ceil($timeout));

        try {
            $handshake = "\x00" . $this->varint(47) . $this->varint(strlen($host)) . $host . pack('n', $port) . $this->varint(1);
            fwrite($socket, $this->varint(strlen($handshake)) . $handshake . "\x01\x00");

            $length = $this->readVarint($socket);
            if ($length === null || $length < 3 || $length > 262144) {
                return null;
            }
            if ($this->readVarint($socket) !== 0) {
                return null;
            }
            $size = $this->readVarint($socket);
            if ($size === null || $size < 2 || $size > 262144) {
                return null;
            }
            $json = '';
            while (strlen($json) < $size) {
                $chunk = fread($socket, $size - strlen($json));
                if ($chunk === false || $chunk === '') {
                    return null;
                }
                $json .= $chunk;
            }
            $data = json_decode($json, true);
            if (!is_array($data) || !isset($data['players']['online'])) {
                return null;
            }

            return [
                'players' => max(0, (int) $data['players']['online']),
                'max_players' => isset($data['players']['max']) && (int) $data['players']['max'] > 0 ? (int) $data['players']['max'] : null,
            ];
        } catch (\Throwable) {
            return null;
        } finally {
            fclose($socket);
        }
    }

    private function varint(int $value): string
    {
        $value &= 0xFFFFFFFF;
        $out = '';
        do {
            $byte = $value & 0x7F;
            $value >>= 7;
            $out .= chr($value !== 0 ? $byte | 0x80 : $byte);
        } while ($value !== 0);

        return $out;
    }

    /**
     * @param resource $socket
     */
    private function readVarint($socket): ?int
    {
        $value = 0;
        for ($i = 0; $i < 5; ++$i) {
            $byte = fread($socket, 1);
            if ($byte === false || $byte === '') {
                return null;
            }
            $byte = ord($byte);
            $value |= ($byte & 0x7F) << (7 * $i);
            if (($byte & 0x80) === 0) {
                return $value;
            }
        }

        return null;
    }
}
