<?php

namespace Pterodactyl\Services\Nodes;

use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Models\ApiKey;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\User;
use Pterodactyl\Services\Admin\ThemeVersionService;
use Pterodactyl\Services\Api\KeyCreationService;
use phpseclib3\Crypt\EC;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;
use Symfony\Component\Process\PhpExecutableFinder;

/**
 * Installs Wings on the machine of a node from the panel: the panel connects to it with SSH (a password used once, or
 * the key of the panel), runs the installer of the theme with the configuration of the node, and writes what it says to
 * a log the page follows. It runs in a process of its own, so the page and the queue are never kept waiting.
 */
class WingsInstaller
{
    // Nobody waits more than this for an installation (Docker and Wings take a few minutes).
    private const LIMIT = 2400;

    public function __construct(
        private Encrypter $encrypter,
        private KeyCreationService $keys,
        private ThemeVersionService $versions,
    ) {
    }

    /**
     * The public key of the panel, made the first time: added once to a machine, it lets the panel in without password.
     */
    public function publicKey(): string
    {
        $private = $this->directory() . '/id_ed25519';
        if (!is_file($private)) {
            $key = EC::createKey('Ed25519');
            file_put_contents($private, $key->toString('OpenSSH', ['comment' => $this->comment()]));
            @chmod($private, 0600);
            file_put_contents($private . '.pub', $key->getPublicKey()->toString('OpenSSH', ['comment' => $this->comment()]));
        }
        if (!is_file($private . '.pub')) {
            $key = PublicKeyLoader::loadPrivateKey((string) file_get_contents($private));
            file_put_contents($private . '.pub', $key->getPublicKey()->toString('OpenSSH', ['comment' => $this->comment()]));
        }

        return trim((string) file_get_contents($private . '.pub'));
    }

    /**
     * Where the installation of a node is: its state and the last lines it wrote.
     */
    public function status(Node $node): array
    {
        $state = $this->readState($node->id);

        // Started but never heard of: the process could not be started on this machine.
        if (($state['state'] ?? null) === 'running' && !is_file($this->path($node->id, 'log'))
            && time() - (int) ($state['started_at'] ?? 0) > 40) {
            @unlink($this->path($node->id, 'request'));
            $state = $this->writeState($node->id, array_merge($state, [
                'state' => 'failed',
                'message' => 'The installation could not start on the panel (the PHP command line is missing, or exec() is disabled).',
                'finished_at' => time(),
            ]));
        }

        // Running for far too long: the process was stopped (the machine of the panel restarted...).
        if (($state['state'] ?? null) === 'running' && time() - (int) ($state['started_at'] ?? 0) > self::LIMIT + 120) {
            $state = $this->writeState($node->id, array_merge($state, [
                'state' => 'failed',
                'message' => 'The installation stopped without an answer.',
                'finished_at' => time(),
            ]));
        }

        return [
            'state' => $state['state'] ?? 'idle',
            'message' => $state['message'] ?? null,
            'host' => $state['host'] ?? null,
            'fingerprint' => $state['fingerprint'] ?? null,
            'started_at' => $state['started_at'] ?? null,
            'finished_at' => $state['finished_at'] ?? null,
            'log' => $this->tail($node->id),
        ];
    }

    /**
     * Starts the installation: checks what was given, writes the request (encrypted, read once then removed) and starts
     * the command that does the work.
     *
     * @throws DisplayException
     */
    public function start(Node $node, User $admin, string $host, int $port, string $user, ?string $password): void
    {
        $state = $this->readState($node->id);
        if (($state['state'] ?? null) === 'running' && time() - (int) ($state['started_at'] ?? 0) < self::LIMIT) {
            throw new DisplayException('An installation is already running for this node.');
        }

        @unlink($this->path($node->id, 'log'));
        file_put_contents($this->path($node->id, 'request'), Crypt::encryptString(json_encode([
            'host' => $host,
            'port' => $port,
            'user' => $user,
            'password' => $password,
            'token' => $this->deployToken($admin),
        ])));
        @chmod($this->path($node->id, 'request'), 0600);
        $this->writeState($node->id, ['state' => 'running', 'message' => null, 'host' => $host, 'fingerprint' => null, 'started_at' => time(), 'finished_at' => null]);

        $php = (new PhpExecutableFinder())->find(false) ?: 'php';
        $command = 'setsid nohup ' . escapeshellarg($php) . ' ' . escapeshellarg(base_path('artisan'))
            . ' p:nodes:install-wings ' . (int) $node->id . ' > /dev/null 2>&1 &';
        try {
            exec($command);
        } catch (\Throwable) {
            // Seen by status() after a few seconds.
        }
    }

    /**
     * Does the installation (in the command started by start()).
     */
    public function run(Node $node): bool
    {
        $file = $this->path($node->id, 'request');
        if (!is_file($file)) {
            return false;
        }
        try {
            $request = json_decode(Crypt::decryptString((string) file_get_contents($file)), true, 512, JSON_THROW_ON_ERROR);
        } finally {
            @unlink($file);
        }

        $token = (string) $request['token'];
        $log = function (string $text) use ($node, $token) {
            file_put_contents($this->path($node->id, 'log'), str_replace($token, '••••••••', $text), FILE_APPEND);
        };
        $fail = function (string $message) use ($node, $log) {
            $log("\n✗ " . $message . "\n");
            $this->writeState($node->id, array_merge($this->readState($node->id), ['state' => 'failed', 'message' => $message, 'finished_at' => time()]));

            return false;
        };

        $log('→ ssh ' . $request['user'] . '@' . $request['host'] . ':' . $request['port'] . "\n");
        try {
            $ssh = new SSH2((string) $request['host'], (int) $request['port'], 15);
            $credential = $request['password'] !== null && $request['password'] !== ''
                ? (string) $request['password']
                : PublicKeyLoader::load((string) file_get_contents($this->directory() . '/id_ed25519'));
            if (!$ssh->login((string) $request['user'], $credential)) {
                return $fail($request['password'] ? 'The machine refused the password.' : 'The machine refused the key of the panel: add it to the authorized keys of the user first.');
            }
        } catch (\Throwable $exception) {
            return $fail('The panel could not connect to the machine: ' . $exception->getMessage());
        }
        unset($credential, $request['password']);

        $fingerprint = $this->fingerprint((string) $ssh->getServerPublicHostKey());
        $this->writeState($node->id, array_merge($this->readState($node->id), ['fingerprint' => $fingerprint]));
        $log('✓ Connected. Key of the machine: ' . $fingerprint . "\n\n");

        $installer = str_replace(' --update', '', $this->versions->updateCommand());
        $script = 'export DEBIAN_FRONTEND=noninteractive; '
            . 'command -v curl >/dev/null 2>&1 || { apt-get update -qq && apt-get install -y -qq curl; } ; '
            . $installer . ' --wings --yes'
            . ' --panel-url=' . escapeshellarg(rtrim((string) config('app.url'), '/'))
            . ' --wings-token=' . escapeshellarg($token)
            . ' --node-id=' . (int) $node->id;
        $command = 'bash -c ' . escapeshellarg($script);
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
            return $fail($status === false ? 'The installation did not finish in time.' : 'The installer stopped with an error (code ' . $status . '): see the log above.');
        }

        $log("\n✓ Wings is installed and started.\n");
        $this->writeState($node->id, array_merge($this->readState($node->id), ['state' => 'done', 'message' => null, 'finished_at' => time()]));

        return true;
    }

    /**
     * The key the installer gives to "wings configure": the application key of the admin with the right to the nodes,
     * made when there is none (like the "Generate Token" button of Pterodactyl).
     */
    private function deployToken(User $admin): string
    {
        $key = ApiKey::query()
            ->where('user_id', $admin->id)
            ->where('key_type', ApiKey::TYPE_APPLICATION)
            ->where('r_nodes', 3)
            ->first();
        if (!$key) {
            $key = $this->keys->setKeyType(ApiKey::TYPE_APPLICATION)->handle([
                'user_id' => $admin->id,
                'memo' => 'Automatically generated node deployment key.',
                'allowed_ips' => [],
            ], ['r_nodes' => 3]);
        }

        return $key->identifier . $this->encrypter->decrypt($key->token);
    }

    private function fingerprint(string $hostKey): string
    {
        $parts = explode(' ', trim($hostKey));
        $blob = base64_decode($parts[1] ?? '', true);

        return $blob ? 'SHA256:' . rtrim(base64_encode(hash('sha256', $blob, true)), '=') : 'unknown';
    }

    /**
     * The last lines of the log, without colours, and with the lines redrawn by progress bars kept as their last state.
     */
    private function tail(int $node): string
    {
        $file = $this->path($node, 'log');
        if (!is_file($file)) {
            return '';
        }
        $size = filesize($file) ?: 0;
        $handle = fopen($file, 'rb');
        if ($handle === false) {
            return '';
        }
        if ($size > 65536) {
            fseek($handle, -65536, SEEK_END);
        }
        $text = (string) stream_get_contents($handle);
        fclose($handle);

        $text = (string) preg_replace('/\e\[[0-9;?]*[ -\/]*[@-~]/', '', $text);
        $lines = array_map(function (string $line) {
            $parts = explode("\r", rtrim($line, "\r"));

            return end($parts);
        }, explode("\n", str_replace("\r\n", "\n", $text)));

        return implode("\n", array_slice($lines, -400));
    }

    private function readState(int $node): array
    {
        $file = $this->path($node, 'state');

        return is_file($file) ? (array) json_decode((string) file_get_contents($file), true) : [];
    }

    private function writeState(int $node, array $state): array
    {
        file_put_contents($this->path($node, 'state'), json_encode($state));

        return $state;
    }

    private function path(int $node, string $kind): string
    {
        return $this->directory() . '/node-' . $node . '.' . $kind;
    }

    private function directory(): string
    {
        $directory = storage_path('app/wings-install');
        if (!is_dir($directory)) {
            mkdir($directory, 0700, true);
        }

        return $directory;
    }

    private function comment(): string
    {
        return 'pterodactyl-panel@' . (parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'panel');
    }
}
