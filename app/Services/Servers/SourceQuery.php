<?php

namespace Pterodactyl\Services\Servers;

/**
 * Asks a game server "who is there" with the Steam / Source query (A2S_INFO), which most games made with the Steam
 * tools answer over UDP: Source games, Unity and Unreal servers that use Steamworks, Rust, ARK, Valheim...
 *
 * Several ports can be asked at once, since the query port is not always the game port. The first that answers wins.
 */
class SourceQuery
{
    private const HEADER = "\xFF\xFF\xFF\xFF";

    private const REQUEST = "\xFF\xFF\xFF\xFFTSource Engine Query\x00";

    /**
     * @param int[] $ports the ports to ask, in order of preference
     *
     * @return array{port: int, name: string, map: string, players: int, max_players: int, bots: int}|null
     */
    public function first(string $host, array $ports, float $timeout = 1.0): ?array
    {
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return null;
        }

        /** @var array<int, resource> $sockets */
        $sockets = [];
        foreach (array_slice(array_values(array_unique(array_filter($ports, fn ($p) => $p > 0 && $p < 65536))), 0, 8) as $port) {
            $socket = @stream_socket_client("udp://{$host}:{$port}", $errno, $errstr, 1);
            if ($socket === false) {
                continue;
            }
            stream_set_blocking($socket, false);
            @fwrite($socket, self::REQUEST);
            $sockets[$port] = $socket;
        }

        // Which sockets have already been sent the challenge they asked for, so it is answered once.
        $challenged = [];
        $found = null;
        $deadline = microtime(true) + $timeout;

        try {
            while ($found === null && $sockets !== [] && microtime(true) < $deadline) {
                $read = array_values($sockets);
                $write = $except = null;
                $left = max(0.0, $deadline - microtime(true));
                if (@stream_select($read, $write, $except, (int) $left, (int) (($left - floor($left)) * 1_000_000)) === false) {
                    break;
                }

                foreach ($read as $socket) {
                    $port = (int) array_search($socket, $sockets, true);
                    $data = @fread($socket, 4096);
                    if ($data === false || $data === '') {
                        continue;
                    }

                    if (($data[4] ?? '') === 'A' && strlen($data) >= 9 && !isset($challenged[$port])) {
                        // Newer servers make the client prove its address first, by repeating a number they send.
                        $challenged[$port] = true;
                        @fwrite($socket, self::REQUEST . substr($data, 5, 4));
                        continue;
                    }

                    $info = $this->parse($data);
                    if ($info !== null) {
                        $found = ['port' => $port] + $info;
                        break;
                    }
                }
            }
        } finally {
            foreach ($sockets as $socket) {
                fclose($socket);
            }
        }

        return $found;
    }

    /**
     * Reads the answer to A2S_INFO. The current format starts with "I", the old GoldSource one with "m".
     *
     * @return array{name: string, map: string, players: int, max_players: int, bots: int}|null
     */
    public function parse(string $data): ?array
    {
        if (strlen($data) < 6 || substr($data, 0, 4) !== self::HEADER) {
            return null;
        }

        $type = $data[4];
        $offset = 5;

        if ($type === 'I') {
            $offset++; // the protocol version
            $name = $this->string($data, $offset);
            $map = $this->string($data, $offset);
            $folder = $this->string($data, $offset);
            $game = $this->string($data, $offset);
            if ($name === null || $map === null || $folder === null || $game === null) {
                return null;
            }
            $offset += 2; // the Steam application id
        } elseif ($type === 'm') {
            $address = $this->string($data, $offset);
            $name = $this->string($data, $offset);
            $map = $this->string($data, $offset);
            $folder = $this->string($data, $offset);
            $game = $this->string($data, $offset);
            if ($address === null || $name === null || $map === null || $folder === null || $game === null) {
                return null;
            }
        } else {
            return null;
        }

        if (strlen($data) < $offset + 3) {
            return null;
        }

        return [
            'name' => $name,
            'map' => $map,
            'players' => ord($data[$offset]),
            'max_players' => ord($data[$offset + 1]),
            'bots' => ord($data[$offset + 2]),
        ];
    }

    /**
     * A text ending with a zero byte, read from the offset, which is moved past it.
     */
    private function string(string $data, int &$offset): ?string
    {
        $end = strpos($data, "\x00", $offset);
        if ($end === false) {
            return null;
        }

        $text = substr($data, $offset, $end - $offset);
        $offset = $end + 1;

        return mb_convert_encoding($text, 'UTF-8', 'UTF-8');
    }
}
