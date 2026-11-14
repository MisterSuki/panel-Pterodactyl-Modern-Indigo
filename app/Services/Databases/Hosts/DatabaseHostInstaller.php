<?php

namespace Pterodactyl\Services\Databases\Hosts;

use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Extensions\DynamicDatabaseConnection;
use Pterodactyl\Models\DatabaseHost;
use Pterodactyl\Services\Nodes\WingsInstaller;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;
use Symfony\Component\Process\PhpExecutableFinder;

/**
 * Makes a database host from the panel: connects to a machine with SSH (a password used once, or the key of the
 * panel), installs MariaDB if it is not there, lets it listen on the network, creates a user that can create databases
 * and users (with a random password), then saves the host in the panel once the panel can connect to it. It runs in a
 * process of its own and writes what it does to a log the page follows.
 */
class DatabaseHostInstaller
{
    private const LIMIT = 1200;

    private const SLOT = 'database-host';

    public function __construct(
        private WingsInstaller $keys,
        private HostCreationService $creation,
        private DynamicDatabaseConnection $dynamic,
        private DatabaseManager $databases,
        private Encrypter $encrypter,
    ) {
    }

    public function publicKey(): string
    {
        return $this->keys->publicKey();
    }

    public function status(): array
    {
        $state = $this->readState();

        if (($state['state'] ?? null) === 'running' && !is_file($this->path('log')) && time() - (int) ($state['started_at'] ?? 0) > 40) {
            @unlink($this->path('request'));
            $state = $this->writeState(array_merge($state, ['state' => 'failed', 'finished_at' => time(),
                'message' => 'The installation could not start on the panel (the PHP command line is missing, or exec() is disabled).']));
        }
        if (($state['state'] ?? null) === 'running' && time() - (int) ($state['started_at'] ?? 0) > self::LIMIT + 120) {
            $state = $this->writeState(array_merge($state, ['state' => 'failed', 'finished_at' => time(), 'message' => 'The installation stopped without an answer.']));
        }

        return [
            'state' => $state['state'] ?? 'idle',
            'message' => $state['message'] ?? null,
            'host_id' => $state['host_id'] ?? null,
            'log' => $this->tail(),
        ];
    }

    /**
     * @param array{name: string, node_id: ?int, host: string, port: int, user: string, password: ?string, db_host: string, db_user: string} $data
     *
     * @throws DisplayException
     */
    public function start(array $data): void
    {
        $state = $this->readState();
        if (($state['state'] ?? null) === 'running' && time() - (int) ($state['started_at'] ?? 0) < self::LIMIT) {
            throw new DisplayException('A database host is already being made.');
        }

        @unlink($this->path('log'));
        file_put_contents($this->path('request'), Crypt::encryptString(json_encode(array_merge($data, [
            // Letters and digits only: it goes into SQL and a shell without quoting problems.
            'db_password' => Str::random(32),
        ]))));
        @chmod($this->path('request'), 0600);
        $this->writeState(['state' => 'running', 'message' => null, 'host_id' => null, 'started_at' => time(), 'finished_at' => null]);

        $php = (new PhpExecutableFinder())->find(false) ?: 'php';
        try {
            exec('setsid nohup ' . escapeshellarg($php) . ' ' . escapeshellarg(base_path('artisan')) . ' p:databases:install-host > /dev/null 2>&1 &');
        } catch (\Throwable) {
            // Seen by status() after a few seconds.
        }
    }

    public function run(): bool
    {
        $file = $this->path('request');
        if (!is_file($file)) {
            return false;
        }
        try {
            $request = json_decode(Crypt::decryptString((string) file_get_contents($file)), true, 512, JSON_THROW_ON_ERROR);
        } finally {
            @unlink($file);
        }

        $secret = (string) $request['db_password'];
        $log = function (string $text) use ($secret) {
            file_put_contents($this->path('log'), str_replace($secret, '••••••••', $text), FILE_APPEND);
        };
        $fail = function (string $message) use ($log) {
            $log("\n✗ " . $message . "\n");
            $this->writeState(array_merge($this->readState(), ['state' => 'failed', 'message' => $message, 'finished_at' => time()]));

            return false;
        };

        $log('→ ssh ' . $request['user'] . '@' . $request['host'] . ':' . $request['port'] . "\n");
        try {
            $ssh = new SSH2((string) $request['host'], (int) $request['port'], 15);
            $credential = !empty($request['password'])
                ? (string) $request['password']
                : PublicKeyLoader::load((string) file_get_contents($this->keys->privateKeyPath()));
            if (!$ssh->login((string) $request['user'], $credential)) {
                return $fail(!empty($request['password']) ? 'The machine refused the password.' : 'The machine refused the key of the panel: add it to the authorized keys of the user first.');
            }
        } catch (\Throwable $exception) {
            return $fail('The panel could not connect to the machine: ' . $exception->getMessage());
        }
        unset($credential);
        $log("✓ Connected.\n\n");

        $script = $this->script((string) $request['db_user'], $secret);
        $command = 'bash -c ' . $this->quote($script);
        if ($request['user'] !== 'root') {
            $command = 'sudo -n ' . $command;
        }

        $ssh->setTimeout(self::LIMIT);
        $ssh->exec($command, function (string $chunk) use ($log) {
            $log($chunk);

            return false;
        });
        $status = $ssh->getExitStatus();
        $ssh->disconnect();
        if ($status !== 0) {
            return $fail($status === false ? 'The installation did not finish in time.' : $this->explain((int) $status));
        }

        // Saved in the panel only once the panel can connect with the new user.
        $log("\n==> Saving the host in the panel (" . $request['db_host'] . ":3306)\n");
        try {
            $host = $this->save($request, $secret);
        } catch (\Throwable $exception) {
            return $fail('MariaDB is ready on the machine, but the panel cannot connect to ' . $request['db_host'] . ':3306 with the new user: open port 3306 on the firewall of the host, or check the address. (' . mb_substr($exception->getMessage(), 0, 200) . ')');
        }

        $log("✓ The database host \"" . $host->name . "\" is ready.\n");
        $this->writeState(array_merge($this->readState(), ['state' => 'done', 'message' => null, 'host_id' => $host->id, 'finished_at' => time()]));

        return true;
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

    /**
     * What runs on the machine: MariaDB installed if needed, listening on the network, and the user made.
     */
    private function script(string $user, string $secret): string
    {
        $sql = "CREATE USER IF NOT EXISTS '{$user}'@'%' IDENTIFIED BY '{$secret}'; "
            . "ALTER USER '{$user}'@'%' IDENTIFIED BY '{$secret}'; "
            . "GRANT ALL PRIVILEGES ON *.* TO '{$user}'@'%' WITH GRANT OPTION; FLUSH PRIVILEGES;";
        $script = implode("\n", [
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

        return $script;
    }

    /**
     * Quotes a word for the bash of the machine (always a Linux machine, whatever the system of the panel).
     */
    private function quote(string $value): string
    {
        return "'" . str_replace("'", "'\''", $value) . "'";
    }

    private function explain(int $status): string
    {
        $end = mb_strtolower(mb_substr($this->tail(), -3000));
        if (str_contains($end, 'access denied for user \'root\'')) {
            return 'MySQL asks a password for root on this machine: the panel cannot create the user. Create it by hand, or let root connect through the socket.';
        }
        if (str_contains($end, 'unable to locate package') || str_contains($end, 'apt-get: command not found')) {
            return 'MariaDB could not be installed: the machine must run Debian or Ubuntu.';
        }
        if (str_contains($end, 'sudo:')) {
            return 'This user cannot use sudo without a password: use root, or allow sudo without password.';
        }

        return 'The installation stopped with an error (code ' . $status . '): see the log above.';
    }

    private function tail(): string
    {
        $file = $this->path('log');
        if (!is_file($file)) {
            return '';
        }
        $text = (string) file_get_contents($file, false, null, max(0, (filesize($file) ?: 0) - 65536));
        $text = (string) preg_replace('/\e\[[0-9;?]*[ -\/]*[@-~]/', '', $text);
        $lines = array_map(function (string $line) {
            $parts = explode("\r", rtrim($line, "\r"));

            return end($parts);
        }, explode("\n", str_replace("\r\n", "\n", $text)));

        return implode("\n", array_slice($lines, -400));
    }

    private function readState(): array
    {
        $file = $this->path('state');

        return is_file($file) ? (array) json_decode((string) file_get_contents($file), true) : [];
    }

    private function writeState(array $state): array
    {
        file_put_contents($this->path('state'), json_encode($state));

        return $state;
    }

    private function path(string $kind): string
    {
        $directory = storage_path('app/wings-install');
        if (!is_dir($directory)) {
            mkdir($directory, 0700, true);
        }

        return $directory . '/' . self::SLOT . '.' . $kind;
    }
}
