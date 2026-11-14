<?php

namespace Pterodactyl\Services\Databases\Hosts;

use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Extensions\DynamicDatabaseConnection;
use Pterodactyl\Models\DatabaseHost;
use Pterodactyl\Services\Nodes\WingsInstaller;
use Pterodactyl\Services\Remote\SshTask;

/**
 * Makes a database host from the panel: connects to a machine with SSH, installs MariaDB if it is not there, lets it
 * listen on the network, creates a user that can create databases and users (with a random password), then saves the
 * host in the panel once the panel can connect to it.
 */
class DatabaseHostInstaller extends SshTask
{
    protected const LIMIT = 1200;

    public function __construct(
        WingsInstaller $keys,
        private HostCreationService $creation,
        private DynamicDatabaseConnection $dynamic,
        private DatabaseManager $databases,
        private Encrypter $encrypter,
    ) {
        parent::__construct($keys);
    }

    /**
     * @param array{name: string, node_id: ?int, host: string, port: int, user: string, password: ?string, db_host: string, db_user: string} $data
     *
     * @throws DisplayException
     */
    public function start(array $data): void
    {
        // Letters and digits only: it goes into SQL and a shell without quoting problems.
        $this->launch(array_merge($data, ['db_password' => Str::random(32)]));
    }

    protected function slot(): string
    {
        return 'database-host';
    }

    protected function command(): string
    {
        return 'p:databases:install-host';
    }

    protected function secrets(array $request): array
    {
        return [(string) $request['db_password']];
    }

    protected function script(array $request): string
    {
        $user = (string) $request['db_user'];
        $secret = (string) $request['db_password'];
        $sql = "CREATE USER IF NOT EXISTS '{$user}'@'%' IDENTIFIED BY '{$secret}'; "
            . "ALTER USER '{$user}'@'%' IDENTIFIED BY '{$secret}'; "
            . "GRANT ALL PRIVILEGES ON *.* TO '{$user}'@'%' WITH GRANT OPTION; FLUSH PRIVILEGES;";

        return implode("\n", [
            'set -e',
            'export DEBIAN_FRONTEND=noninteractive',
            'if ! command -v mysql >/dev/null 2>&1 && ! command -v mariadb >/dev/null 2>&1; then',
            '  echo "==> Installing MariaDB"',
            '  apt-get update -qq',
            '  apt-get install -y -qq mariadb-server',
            'else',
            '  echo "==> MariaDB (or MySQL) is already installed"',
            'fi',
            'echo "==> Listening on the network (port 3306)"',
            'dir=/etc/mysql/mariadb.conf.d; [ -d "$dir" ] || dir=/etc/mysql/conf.d; mkdir -p "$dir"',
            'printf "[mysqld]\\nbind-address=0.0.0.0\\n" > "$dir/99-pterodactyl-databases.cnf"',
            'service=mariadb; systemctl list-unit-files mariadb.service >/dev/null 2>&1 || service=mysql',
            'systemctl enable "$service" >/dev/null 2>&1 || true',
            'systemctl restart "$service"',
            'echo "==> Creating the user ' . $user . '"',
            'client=mariadb; command -v mariadb >/dev/null 2>&1 || client=mysql',
            '"$client" -u root -e ' . $this->quote($sql),
            'if command -v ufw >/dev/null 2>&1 && ufw status 2>/dev/null | grep -q "Status: active"; then ufw allow 3306/tcp >/dev/null && echo "  Firewall: port 3306 opened"; fi',
            'echo "  Done"',
        ]);
    }

    /**
     * Saved in the panel only once the panel can connect with the new user.
     */
    protected function finish(array $request, callable $log): ?string
    {
        $log("\n==> Saving the host in the panel (" . $request['db_host'] . ":3306)\n");
        try {
            $host = $this->save($request, (string) $request['db_password']);
        } catch (\Throwable $exception) {
            return 'MariaDB is ready on the machine, but the panel cannot connect to ' . $request['db_host'] . ':3306 with the new user: open port 3306 on the firewall of the host, or check the address. (' . mb_substr($exception->getMessage(), 0, 200) . ')';
        }
        $log('✓ The database host "' . $host->name . "\" is ready.\n");
        $this->remember(['host_id' => $host->id]);

        return null;
    }

    protected function explainLog(string $end): ?string
    {
        if (str_contains($end, 'access denied for user \'root\'')) {
            return 'MySQL asks a password for root on this machine: the panel cannot create the user. Create it by hand, or let root connect through the socket.';
        }
        if (str_contains($end, 'unable to locate package') || str_contains($end, 'apt-get: command not found')) {
            return 'MariaDB could not be installed: the machine must run Debian or Ubuntu.';
        }

        return parent::explainLog($end);
    }

    /**
     * Creates the host, or gives the new password to the one already saved for the same address and user.
     */
    private function save(array $request, string $secret): DatabaseHost
    {
        $data = [
            'name' => (string) $request['name'],
            'host' => (string) $request['db_host'],
            'port' => 3306,
            'username' => (string) $request['db_user'],
            'password' => $secret,
            'node_id' => $request['node_id'] ?: null,
        ];

        /** @var DatabaseHost|null $existing */
        $existing = DatabaseHost::query()->where('host', $data['host'])->where('port', 3306)->where('username', $data['username'])->first();
        if (!$existing) {
            return $this->creation->handle($data);
        }

        $existing->forceFill(['password' => $this->encrypter->encrypt($secret), 'name' => $data['name'], 'node_id' => $data['node_id']])->save();
        $this->dynamic->set('dynamic', $existing);
        $this->databases->connection('dynamic')->select('SELECT 1 FROM dual');

        return $existing;
    }
}
