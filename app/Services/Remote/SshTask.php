<?php

namespace Pterodactyl\Services\Remote;

use Illuminate\Support\Facades\Crypt;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Nodes\WingsInstaller;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;
use Symfony\Component\Process\PhpExecutableFinder;

/**
 * Something the panel does on a machine over SSH (a password used once, or the key of the panel): it runs in a process
 * of its own, started by an artisan command, and writes what the machine says to a log the page follows. The request
 * (with the password) is encrypted, read once, then removed.
 */
abstract class SshTask
{
    protected const LIMIT = 1800;

    public function __construct(protected WingsInstaller $keys)
    {
    }

    /**
     * The name of the files of this task (one task of a kind at a time).
     */
    abstract protected function slot(): string;

    /**
     * The artisan command that runs the task.
     */
    abstract protected function command(): string;

    /**
     * The bash script run on the machine, as root.
     */
    abstract protected function script(array $request): string;

    /**
     * What to do once the script worked; returns why it failed, or null.
     */
    protected function finish(array $request, callable $log): ?string
    {
        return null;
    }

    /**
     * Why the script stopped, from the end of its log; null for the general sentence.
     */
    protected function explainLog(string $end): ?string
    {
        if (str_contains($end, 'sudo:')) {
            return 'This user cannot use sudo without a password: use root, or allow sudo without password.';
        }

        return null;
    }

    /**
     * Words of the log to hide (secrets of the request).
     *
     * @return array<int, string>
     */
    protected function secrets(array $request): array
    {
        return [];
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
        if (($state['state'] ?? null) === 'running' && time() - (int) ($state['started_at'] ?? 0) > static::LIMIT + 120) {
            $state = $this->writeState(array_merge($state, ['state' => 'failed', 'finished_at' => time(), 'message' => 'The installation stopped without an answer.']));
        }

        return array_merge($state['extra'] ?? [], [
            'state' => $state['state'] ?? 'idle',
            'message' => $state['message'] ?? null,
            'log' => $this->tail(),
        ]);
    }

    /**
     * @param array{host: string, port: int, user: string, password: ?string} $data and what the task needs
     *
     * @throws DisplayException
     */
    public function launch(array $data): void
    {
        $state = $this->readState();
        if (($state['state'] ?? null) === 'running' && time() - (int) ($state['started_at'] ?? 0) < static::LIMIT) {
            throw new DisplayException('An installation is already running.');
        }

        @unlink($this->path('log'));
        file_put_contents($this->path('request'), Crypt::encryptString(json_encode($data)));
        @chmod($this->path('request'), 0600);
        $this->writeState(['state' => 'running', 'message' => null, 'extra' => [], 'started_at' => time(), 'finished_at' => null]);

        $php = (new PhpExecutableFinder())->find(false) ?: 'php';
        try {
            exec('setsid nohup ' . escapeshellarg($php) . ' ' . escapeshellarg(base_path('artisan')) . ' ' . $this->command() . ' > /dev/null 2>&1 &');
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

        $secrets = array_values(array_filter($this->secrets($request), fn ($s) => $s !== ''));
        $log = function (string $text) use ($secrets) {
            file_put_contents($this->path('log'), $secrets ? str_replace($secrets, '••••••••', $text) : $text, FILE_APPEND);
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

        $command = 'bash -c ' . $this->quote($this->script($request));
        if ($request['user'] !== 'root') {
            $command = 'sudo -n ' . $command;
        }
        $ssh->setTimeout(static::LIMIT);
        $ssh->exec($command, function (string $chunk) use ($log) {
            $log($chunk);

            return false;
        });
        $status = $ssh->getExitStatus();
        $ssh->disconnect();
        if ($status !== 0) {
            if ($status === false) {
                return $fail('The installation did not finish in time.');
            }

            return $fail($this->explainLog(mb_strtolower(mb_substr($this->tail(), -4000))) ?? 'The installation stopped with an error (code ' . $status . '): see the log above.');
        }

        try {
            $problem = $this->finish($request, $log);
        } catch (\Throwable $exception) {
            $problem = mb_substr($exception->getMessage(), 0, 300);
        }
        if ($problem !== null) {
            return $fail($problem);
        }

        $this->writeState(array_merge($this->readState(), ['state' => 'done', 'message' => null, 'finished_at' => time()]));

        return true;
    }

    /**
     * Keeps a value the page should get with the state (the id of what was made...).
     */
    protected function remember(array $extra): void
    {
        $state = $this->readState();
        $state['extra'] = array_merge($state['extra'] ?? [], $extra);
        $this->writeState($state);
    }

    /**
     * Quotes a word for the bash of the machine (always a Linux machine, whatever the system of the panel).
     */
    protected function quote(string $value): string
    {
        return "'" . str_replace("'", "'\\''", $value) . "'";
    }

    protected function tail(): string
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

        return $directory . '/' . $this->slot() . '.' . $kind;
    }
}
