<?php

namespace Pterodactyl\Services\Assistant;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Models\EggVariable;
use Pterodactyl\Models\Permission;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\User;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Services\Servers\StartupCommandService;

/**
 * The AI assistant of a server: it gets what the server is (the game, its egg, its image, its start command, its
 * settings with the secrets hidden, its files and the end of its console) and answers the questions of its owner about
 * bugs, crashes and settings, through the API of Groq.
 */
class ServerAssistant
{
    private const ENDPOINT = 'https://api.groq.com/openai/v1/chat/completions';

    // What is sent at most: lines of console, characters of console, messages of the conversation, characters of one.
    private const CONSOLE_LINES = 200;
    private const CONSOLE_CHARS = 14000;
    private const HISTORY = 12;
    private const MESSAGE_CHARS = 4000;

    // The settings of a server whose value is never sent (keys, passwords, licences...).
    private const SECRET = '/(KEY|TOKEN|PASS|SECRET|LICEN[CS]E|RCON|AUTH|WEBHOOK|API|STEAM_ACC|PRIVATE|CREDENTIAL)/i';

    public function __construct(
        private AssistantSettings $settings,
        private StartupCommandService $startup,
        private DaemonFileRepository $files,
    ) {
    }

    /**
     * How many questions the person may still ask today (null when there is no limit).
     */
    public function remaining(User $user): ?int
    {
        $daily = $this->settings->daily();

        return $daily === 0 ? null : max(0, $daily - (int) Cache::get($this->counterKey($user), 0));
    }

    /**
     * Answers the last question of the conversation.
     *
     * @param array<int, array{role: string, content: string}> $messages
     *
     * @throws DisplayException
     */
    public function ask(Server $server, User $user, array $messages, string $console, ?string $status): string
    {
        if (!$this->settings->allows($user)) {
            throw new DisplayException('The AI assistant is not available.');
        }
        $remaining = $this->remaining($user);
        if ($remaining !== null && $remaining <= 0) {
            throw new DisplayException('You asked the AI assistant all the questions allowed for today. Come back tomorrow.');
        }

        $conversation = [
            ['role' => 'system', 'content' => $this->instructions($user)],
            ['role' => 'system', 'content' => $this->context($server, $user, $console, $status)],
        ];
        foreach (array_slice($messages, -self::HISTORY) as $message) {
            $conversation[] = [
                'role' => $message['role'] === 'assistant' ? 'assistant' : 'user',
                'content' => mb_substr($this->hideSecrets((string) $message['content']), 0, self::MESSAGE_CHARS),
            ];
        }

        $answer = $this->call($conversation, 4096);

        $key = $this->counterKey($user);
        Cache::add($key, 0, now()->endOfDay());
        Cache::increment($key);

        return $answer;
    }

    /**
     * A tiny question to check the key and the model (Admin > AI assistant).
     *
     * @throws DisplayException
     */
    public function test(): string
    {
        return $this->call([['role' => 'user', 'content' => 'Answer with the single word OK.']], 300);
    }

    /**
     * What the assistant is and how it answers.
     */
    public function instructions(User $user): string
    {
        $language = ['fr' => 'French', 'en' => 'English', 'de' => 'German', 'es' => 'Spanish', 'it' => 'Italian', 'pt' => 'Portuguese', 'nl' => 'Dutch', 'pl' => 'Polish'][substr((string) ($user->language ?: 'en'), 0, 2)] ?? 'the language of the user';

        return implode("\n", [
            'You are the assistant of a game server hosting panel (Pterodactyl). You help the owner of ONE server, whose details and console are given below, to understand and fix problems: crashes, errors, mods and plugins, configuration, performance, connection issues, and how to use the panel.',
            '',
            'How you work:',
            '- First identify the game or software (from the egg, the Docker image, the start command and the files) and use what you know about it specifically: its usual errors, its configuration files, its mods, its versions.',
            '- Read the console carefully. Quote the exact line(s) that show the problem, explain in simple words what they mean and the most likely cause.',
            '- Give concrete, numbered steps the user can do in the panel: the tabs are Console, Files (edit config files), Databases, Schedules, Users, Backups, Network (ports), Startup (start command, variables, Docker image / version), Settings (reinstall, SFTP) and Activity. Name the exact file and the exact setting to change.',
            '- Several errors: start with the one that stops the server, then the others. Repeated warnings that do not stop the server can be mentioned briefly.',
            '- If the information is not enough, say what is missing (for example the full error, a config file or the version) and ask for it. Never invent a cause, a file or a setting: say when you are not sure.',
            '- Never ask for passwords, tokens or license keys, and never repeat one. Values shown as (hidden) are secret.',
            '- The user is often a beginner: be clear and short, avoid jargon, explain the commands. Use Markdown: short paragraphs, numbered lists, `code` for files, commands and values, code blocks for config.',
            '- Stay on the subject of this server, its game and the panel.',
            '',
            "Always answer in $language.",
        ]);
    }

    /**
     * What the assistant knows about the server.
     */
    public function context(Server $server, User $user, string $console, ?string $status): string
    {
        $server->loadMissing(['egg', 'nest', 'allocation']);
        $egg = $server->egg;
        $lines = ['# The server', '- Name: ' . $server->name];
        if ($server->description) {
            $lines[] = '- Description: ' . Str::limit((string) $server->description, 200);
        }
        $lines[] = '- Game / software (nest and egg): ' . ($server->nest->name ?? '?') . ' / ' . ($egg->name ?? '?');
        if ($egg && $egg->description) {
            $lines[] = '- About the egg: ' . Str::limit(trim((string) preg_replace('/\s+/', ' ', strip_tags((string) $egg->description))), 300);
        }
        $lines[] = '- Docker image: ' . $server->image;
        $lines[] = '- State: ' . ($status ?: 'unknown');
        $lines[] = sprintf(
            '- Limits: memory %s, disk %s, CPU %s, swap %s MB',
            $server->memory ? $server->memory . ' MB' : 'unlimited',
            $server->disk ? $server->disk . ' MB' : 'unlimited',
            $server->cpu ? $server->cpu . '% (100% = 1 core)' : 'unlimited',
            $server->swap
        );
        if ($server->allocation) {
            $lines[] = '- Main port: ' . $server->allocation->port;
        }

        if ($user->can(Permission::ACTION_STARTUP_READ, $server)) {
            $lines[] = '- Start command: ' . $this->hideSecrets($this->startup->handle($server, false));
            $variables = $server->variables()->get()->filter(fn (EggVariable $v) => $v->user_viewable);
            if ($variables->isNotEmpty()) {
                $lines[] = '';
                $lines[] = '# Startup variables (Startup tab)';
                foreach ($variables as $variable) {
                    $value = (string) ($variable->server_value ?? $variable->default_value);
                    $secret = preg_match(self::SECRET, $variable->env_variable . ' ' . $variable->name) === 1;
                    $lines[] = sprintf('- %s (%s) = %s', $variable->name, $variable->env_variable, $secret ? ($value === '' ? '(empty)' : '(hidden)') : Str::limit($value, 200));
                }
            }
        }

        $files = $this->rootFiles($server, $user);
        if ($files !== null) {
            $lines[] = '';
            $lines[] = '# Files at the root of the server';
            $lines[] = $files === [] ? '(empty)' : implode(', ', $files);
        }

        $lines[] = '';
        $lines[] = '# End of the console (the most recent lines last)';
        $text = $this->cleanConsole($console);
        $lines[] = $text === '' ? '(empty: the server printed nothing since the page was opened)' : "```\n" . $text . "\n```";

        return implode("\n", $lines);
    }

    /**
     * The last lines of the console, without colours, with secrets hidden, not too long.
     */
    public function cleanConsole(string $console): string
    {
        $text = (string) preg_replace('/\x1b\[[0-9;?]*[A-Za-z]/', '', $console);
        $text = str_replace("\r", '', $text);
        $lines = array_slice(explode("\n", rtrim($text)), -self::CONSOLE_LINES);
        $text = $this->hideSecrets(implode("\n", $lines));
        if (mb_strlen($text) > self::CONSOLE_CHARS) {
            $text = '[...]' . mb_substr($text, -self::CONSOLE_CHARS);
        }

        return trim($text, "\n");
    }

    /**
     * Hides what looks like a key, a token or a password.
     */
    public function hideSecrets(string $text): string
    {
        $text = (string) preg_replace('/\b(cfxk_[A-Za-z0-9_]{10,}|gsk_[A-Za-z0-9]{20,}|ptl[ac]_[A-Za-z0-9]{20,}|sk-[A-Za-z0-9_-]{20,}|gh[pousr]_[A-Za-z0-9]{20,}|xox[abprs]-[A-Za-z0-9-]{10,})/', '(hidden)', $text);
        $text = (string) preg_replace('#(https://(?:discord(?:app)?\.com)/api/webhooks/)[^\s"\']+#i', '$1(hidden)', $text);

        return (string) preg_replace('/((?:password|passwd|pwd|token|secret|api[_-]?key|license[_-]?key|rcon[_-]?password)\s*[=:]\s*)("?)[^\s"\']+\2/i', '$1(hidden)', $text);
    }

    /**
     * @param array<int, array{role: string, content: string}> $messages
     *
     * @throws DisplayException
     */
    private function call(array $messages, int $maxTokens): string
    {
        $key = $this->settings->key();
        if ($key === null) {
            throw new DisplayException('The AI assistant has no Groq key.');
        }
        $model = $this->settings->model();

        try {
            $response = Http::withToken($key)->acceptJson()->timeout(90)->connectTimeout(10)
                ->post(self::ENDPOINT, [
                    'model' => $model,
                    'messages' => $messages,
                    'temperature' => 0.3,
                    'max_completion_tokens' => $maxTokens,
                ]);
        } catch (\Throwable) {
            throw new DisplayException('The AI could not be reached. Try again in a moment.');
        }

        if ($response->status() === 401) {
            throw new DisplayException('The Groq key of the AI assistant is wrong (Admin > AI assistant).');
        }
        if ($response->status() === 429) {
            throw new DisplayException('The AI is busy right now (limit of Groq). Try again in a minute.');
        }
        if ($response->status() === 404 || str_contains((string) $response->json('error.code'), 'model_not_found')) {
            throw new DisplayException("The AI model \"$model\" is not available on Groq: choose another one in Admin > AI assistant.");
        }
        if (!$response->successful()) {
            throw new DisplayException('The AI did not answer (' . $response->status() . '). Try again in a moment.');
        }

        $answer = trim((string) $response->json('choices.0.message.content'));
        if ($answer === '') {
            throw new DisplayException('The AI gave an empty answer. Ask again, a bit differently.');
        }

        return $answer;
    }

    /**
     * The names of the files and folders at the root of the server (null when they cannot be read).
     *
     * @return array<int, string>|null
     */
    private function rootFiles(Server $server, User $user): ?array
    {
        if (!$user->can(Permission::ACTION_FILE_READ, $server)) {
            return null;
        }
        try {
            $list = $this->files->setServer($server)->getDirectory('/');
        } catch (\Throwable) {
            return null;
        }

        return collect($list)->take(80)->map(fn ($item) => ((string) ($item['name'] ?? '')) . (($item['file'] ?? true) ? '' : '/'))
            ->filter(fn ($name) => $name !== '' && $name !== '/')->values()->all();
    }

    private function counterKey(User $user): string
    {
        return 'assistant:asked:' . $user->id . ':' . now()->format('Y-m-d');
    }
}
