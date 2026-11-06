<?php

namespace Pterodactyl\Services\Subdomains;

/**
 * Asks the name servers of a domain themselves. Public resolvers remember "this name does not exist" for up to half an
 * hour, so a record created just after a first check would stay invisible; the name servers always have the truth.
 */
class AuthoritativeDns
{
    private const TIMEOUT_SECONDS = 2;

    /**
     * @param string[] $servers the names of the name servers of the zone
     *
     * @return string[]|null the IPv4 addresses of the name, or null when no name server answered
     */
    public function addresses(string $hostname, array $servers): ?array
    {
        foreach (array_slice($servers, 0, 3) as $server) {
            $ip = gethostbyname(rtrim($server, '.'));
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
                continue;
            }
            $answer = $this->ask($ip, $hostname);
            if ($answer !== null) {
                return $this->parse($answer);
            }
        }

        return null;
    }

    private function ask(string $ip, string $hostname): ?string
    {
        $socket = @stream_socket_client("udp://{$ip}:53", $errno, $error, self::TIMEOUT_SECONDS);
        if ($socket === false) {
            return null;
        }
        stream_set_timeout($socket, self::TIMEOUT_SECONDS);

        $id = random_int(1, 65535);
        $question = pack('nnnnnn', $id, 0, 1, 0, 0, 0);
        foreach (explode('.', $hostname) as $label) {
            $question .= chr(strlen($label)) . $label;
        }
        $question .= "\0" . pack('nn', 1, 1);

        try {
            @fwrite($socket, $question);
            $answer = @fread($socket, 1500);
        } finally {
            fclose($socket);
        }

        // The answer of another question is not an answer.
        return is_string($answer) && strlen($answer) >= 12 && unpack('n', $answer)[1] === $id ? $answer : null;
    }

    /**
     * The A records of an answer. A name that does not exist is an answer too: an empty list.
     *
     * @return string[]
     */
    private function parse(string $data): array
    {
        $answers = unpack('n', substr($data, 6, 2))[1];
        $offset = 12;
        $questions = unpack('n', substr($data, 4, 2))[1];
        for ($i = 0; $i < $questions; ++$i) {
            $offset = $this->skipName($data, $offset) + 4;
        }

        $ips = [];
        for ($i = 0; $i < $answers && $offset < strlen($data); ++$i) {
            $offset = $this->skipName($data, $offset);
            if ($offset + 10 > strlen($data)) {
                break;
            }
            $record = unpack('ntype/nclass/Nttl/nlength', substr($data, $offset, 10));
            $offset += 10;
            if ($record['type'] === 1 && $record['length'] === 4) {
                $ips[] = implode('.', array_map('ord', str_split(substr($data, $offset, 4))));
            }
            $offset += $record['length'];
        }

        return $ips;
    }

    private function skipName(string $data, int $offset): int
    {
        $length = strlen($data);
        while ($offset < $length) {
            $byte = ord($data[$offset]);
            if ($byte === 0) {
                return $offset + 1;
            }
            if (($byte & 0xC0) === 0xC0) {
                return $offset + 2;
            }
            $offset += $byte + 1;
        }

        return $offset;
    }
}
