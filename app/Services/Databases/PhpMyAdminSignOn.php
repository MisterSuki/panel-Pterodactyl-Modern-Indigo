<?php

namespace Pterodactyl\Services\Databases;

use Illuminate\Support\Str;
use Pterodactyl\Models\Database;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Encryption\Encrypter;

/**
 * Lets a user open one of their server databases in phpMyAdmin without typing the credentials.
 *
 * The panel hands out a link with a random, single-use token that is valid for one minute. The
 * sign-in script of phpMyAdmin (installed next to it) trades the token for the credentials by
 * calling the panel, proving who it is with a shared secret. The database password therefore never
 * appears in a link, in the browser history or in a server log.
 */
class PhpMyAdminSignOn
{
    public const TOKEN_PATTERN = '/^[A-Za-z0-9]{64}$/';

    private const CACHE_PREFIX = 'phpmyadmin-signon:';

    private const TTL_SECONDS = 60;

    public function __construct(private Encrypter $encrypter, private CacheRepository $cache)
    {
    }

    /**
     * The address phpMyAdmin is served at, or null when it is not set up.
     */
    public static function url(): ?string
    {
        $value = config('pterodactyl.phpmyadmin.url');

        return is_string($value) && trim($value) !== '' ? rtrim(trim($value), '/') : null;
    }

    /**
     * The secret shared with phpMyAdmin's sign-in script.
     */
    public static function secret(): ?string
    {
        $value = config('pterodactyl.phpmyadmin.secret');

        return is_string($value) && strlen($value) >= 16 ? $value : null;
    }

    /**
     * The feature is only offered when both the address and the secret are configured.
     */
    public static function enabled(): bool
    {
        return self::url() !== null && self::secret() !== null;
    }

    /**
     * Creates a single-use sign-in link for the given database.
     */
    public function issue(Database $database): string
    {
        $token = Str::random(64);

        // Only a hash of the token is stored, so reading the cache does not give anyone a usable link.
        $this->cache->put(self::CACHE_PREFIX . hash('sha256', $token), ['database_id' => $database->id], self::TTL_SECONDS);

        return self::url() . '/signon.php?token=' . $token;
    }

    /**
     * Trades a token for the credentials. A token works once: it is removed as it is read.
     *
     * @return array{user: string, password: string, host: string, port: int, database: string}|null
     */
    public function redeem(string $token): ?array
    {
        if (preg_match(self::TOKEN_PATTERN, $token) !== 1) {
            return null;
        }

        $entry = $this->cache->pull(self::CACHE_PREFIX . hash('sha256', $token));
        if (!is_array($entry) || !isset($entry['database_id'])) {
            return null;
        }

        $database = $this->findDatabase((int) $entry['database_id']);

        return $database ? $this->credentialsFor($database) : null;
    }

    /**
     * @return array{user: string, password: string, host: string, port: int, database: string}
     */
    public function credentialsFor(Database $database): array
    {
        $host = $database->host;

        return [
            'user' => $database->username,
            'password' => $this->encrypter->decrypt($database->password),
            'host' => $host->host,
            'port' => (int) $host->port,
            'database' => $database->database,
        ];
    }

    protected function findDatabase(int $id): ?Database
    {
        return Database::query()->with('host')->find($id);
    }
}
