<?php

namespace Pterodactyl\Services\Assistant;

use Illuminate\Support\Collection;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Models\AssistantFileChange;
use Pterodactyl\Models\Permission;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\User;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;

/**
 * The files of a server for the AI assistant: what it may read (secrets hidden), the changes it proposes (nothing is
 * written then), and applying a change or putting the file back as it was, when the person asks.
 */
class AssistantFiles
{
    // The biggest file the AI reads, and how much of it it is given.
    private const READ_BYTES = 1048576;
    private const READ_CHARS = 24000;
    // The biggest file a change may write.
    private const WRITE_BYTES = 1048576;

    public function __construct(private DaemonFileRepository $files)
    {
    }

    /**
     * The tools the AI may call (OpenAI format, understood by Groq).
     */
    public static function tools(): array
    {
        $string = fn (string $description) => ['type' => 'string', 'description' => $description];

        return [
            ['type' => 'function', 'function' => [
                'name' => 'list_directory',
                'description' => 'Lists the files and folders of a folder of the server.',
                'parameters' => ['type' => 'object', 'properties' => ['path' => $string('Folder, from the root of the server, like "/" or "/plugins".')], 'required' => ['path']],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'read_file',
                'description' => 'Reads a text file of the server (configuration, logs, scripts). Secret values are shown as (hidden).',
                'parameters' => ['type' => 'object', 'properties' => ['path' => $string('File, from the root of the server, like "/server.properties".')], 'required' => ['path']],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'propose_change',
                'description' => 'Proposes to change an existing text file by replacing exact pieces of it. Nothing is written: the user sees the change and applies it, and can put the file back later. Read the file first. Each "search" must be copied exactly from the file and appear only once in it; keep it short (the lines to change plus a line around if needed). Never include (hidden) values.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'path' => $string('The file to change.'),
                    'summary' => $string('What the change does, in a short sentence in the language of the user.'),
                    'edits' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                        'search' => $string('Exact text of the file to replace.'),
                        'replace' => $string('The new text.'),
                    ], 'required' => ['search', 'replace']]],
                ], 'required' => ['path', 'summary', 'edits']],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'create_file',
                'description' => 'Proposes to create a new text file that does not exist yet. Nothing is written until the user applies it.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'path' => $string('The new file.'),
                    'summary' => $string('What the file is for, in a short sentence in the language of the user.'),
                    'content' => $string('The whole content of the file.'),
                ], 'required' => ['path', 'summary', 'content']],
            ]],
        ];
    }

    /**
     * Runs a tool asked by the AI. What it gives back is for the AI; the changes proposed are added to $proposed.
     *
     * @param array<int, AssistantFileChange> $proposed
     */
    public function run(Server $server, User $user, string $tool, array $arguments, array &$proposed): string
    {
        try {
            return match ($tool) {
                'list_directory' => $this->listDirectory($server, $user, (string) ($arguments['path'] ?? '/')),
                'read_file' => $this->readFile($server, $user, (string) ($arguments['path'] ?? '')),
                'propose_change' => $this->proposeChange($server, $user, $arguments, $proposed),
                'create_file' => $this->proposeFile($server, $user, $arguments, $proposed),
                default => 'Error: this tool does not exist.',
            };
        } catch (DisplayException $exception) {
            return 'Error: ' . $exception->getMessage();
        } catch (\Throwable) {
            return 'Error: the server did not answer (it may be offline or the path does not exist).';
        }
    }

    /**
     * A path inside the server: starts with "/", no "..".
     *
     * @throws DisplayException
     */
    public function cleanPath(string $path): string
    {
        $parts = [];
        foreach (explode('/', str_replace('\\', '/', trim($path))) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                throw new DisplayException('paths with ".." are not allowed.');
            }
            $parts[] = $part;
        }

        return '/' . implode('/', $parts);
    }

    /**
     * Writes the change in the file, if the file is still as the AI read it.
     *
     * @throws DisplayException
     */
    public function apply(Server $server, User $user, AssistantFileChange $change): AssistantFileChange
    {
        if ($change->state !== AssistantFileChange::PROPOSED) {
            throw new DisplayException('This change was already applied or refused.');
        }
        $this->allow($user, $server, $change->before === null ? Permission::ACTION_FILE_CREATE : Permission::ACTION_FILE_UPDATE);
        $current = $this->current($server, $change->path);
        if ($change->before === null && $current !== null) {
            throw new DisplayException('This file exists now: ask the assistant again.');
        }
        if ($change->before !== null && $current !== $change->before) {
            throw new DisplayException($current === null ? 'This file does not exist any more.' : 'The file was changed since the assistant read it: ask it again.');
        }

        $this->files->setServer($server)->putContent($change->path, $change->after);
        $change->forceFill(['state' => AssistantFileChange::APPLIED, 'applied_at' => now()])->save();
        Activity::event('server:file.write')->property('file', $change->path)->property('by', 'assistant')->log();

        return $change;
    }

    /**
     * Puts the file back as it was before the change (deletes it if the change created it). $force: even if the file
     * was changed again since.
     *
     * @throws DisplayException
     */
    public function revert(Server $server, User $user, AssistantFileChange $change, bool $force): AssistantFileChange
    {
        if ($change->state !== AssistantFileChange::APPLIED) {
            throw new DisplayException('This change is not applied.');
        }
        $this->allow($user, $server, $change->before === null ? Permission::ACTION_FILE_DELETE : Permission::ACTION_FILE_UPDATE);
        $current = $this->current($server, $change->path);
        if (!$force && $current !== $change->after) {
            throw new ChangedSinceException();
        }

        $files = $this->files->setServer($server);
        if ($change->before === null) {
            if ($current !== null) {
                $files->deleteFiles(dirname($change->path), [basename($change->path)]);
            }
        } else {
            $files->putContent($change->path, $change->before);
        }
        $change->forceFill(['state' => AssistantFileChange::REVERTED, 'reverted_at' => now()])->save();
        Activity::event('server:file.write')->property('file', $change->path)->property('by', 'assistant (put back)')->log();

        return $change;
    }

    /**
     * The changes of a server, newest first (the last 30 days).
     *
     * @return Collection<int, AssistantFileChange>
     */
    public function history(Server $server): Collection
    {
        AssistantFileChange::query()->where('server_id', $server->id)->where('created_at', '<', now()->subDays(30))->delete();

        return AssistantFileChange::query()->where('server_id', $server->id)->orderByDesc('id')->limit(40)->get();
    }

    public function present(AssistantFileChange $change): array
    {
        return [
            'id' => $change->id,
            'path' => $change->path,
            'summary' => $change->summary,
            'state' => $change->state,
            'created' => $change->before === null,
            'hunks' => json_decode($change->hunks, true) ?: [],
            'created_at' => $change->created_at?->toAtomString(),
            'applied_at' => $change->applied_at?->toAtomString(),
            'reverted_at' => $change->reverted_at?->toAtomString(),
        ];
    }

    private function listDirectory(Server $server, User $user, string $path): string
    {
        $this->allow($user, $server, Permission::ACTION_FILE_READ);
        $list = $this->files->setServer($server)->getDirectory($this->cleanPath($path));
        $lines = collect($list)->take(150)->map(fn ($item) => ((string) ($item['name'] ?? '')) . (($item['file'] ?? true) ? ' (' . (int) ($item['size'] ?? 0) . ' bytes)' : '/'));

        return $lines->isEmpty() ? '(empty folder)' : $lines->implode("\n");
    }

    private function readFile(Server $server, User $user, string $path): string
    {
        $this->allow($user, $server, Permission::ACTION_FILE_READ);
        $content = $this->files->setServer($server)->getContent($this->cleanPath($path), self::READ_BYTES);
        if (str_contains($content, "\0")) {
            return 'Error: this is not a text file.';
        }
        $text = SecretMask::hide($content);
        if (mb_strlen($text) > self::READ_CHARS) {
            $text = mb_substr($text, 0, self::READ_CHARS) . "\n[... the rest of the file is cut ...]";
        }

        return $text;
    }

    /**
     * @param array<int, AssistantFileChange> $proposed
     *
     * @throws DisplayException
     */
    private function proposeChange(Server $server, User $user, array $arguments, array &$proposed): string
    {
        $path = $this->cleanPath((string) ($arguments['path'] ?? ''));
        $edits = array_values(array_filter((array) ($arguments['edits'] ?? []), 'is_array'));
        if ($path === '/' || !$edits) {
            throw new DisplayException('give the file and at least one edit.');
        }
        $this->checkCount($proposed);
        $before = $this->current($server, $path);
        if ($before === null) {
            throw new DisplayException("$path does not exist (use create_file for a new file).");
        }
        if (str_contains($before, "\0")) {
            throw new DisplayException('this is not a text file.');
        }

        $after = $before;
        $hunks = [];
        foreach ($edits as $index => $edit) {
            $search = (string) ($edit['search'] ?? '');
            $replace = (string) ($edit['replace'] ?? '');
            if (str_contains($search, SecretMask::HIDDEN) || str_contains($replace, SecretMask::HIDDEN)) {
                throw new DisplayException('an edit contains a hidden value: choose a search text without it, and never write (hidden).');
            }
            // The AI often writes \n where the file has \r\n.
            if (str_contains($after, "\r\n") && !str_contains($search, "\r\n")) {
                $search = str_replace("\n", "\r\n", $search);
                $replace = str_replace("\n", "\r\n", $replace);
            }
            if (trim($search) === '') {
                throw new DisplayException('edit ' . ($index + 1) . ' has an empty search.');
            }
            $count = substr_count($after, $search);
            if ($count !== 1) {
                throw new DisplayException('edit ' . ($index + 1) . ($count === 0 ? ': the search text is not in the file (copy it exactly, read the file again).' : ": the search text is $count times in the file (add lines around to make it unique)."));
            }
            $after = str_replace($search, $replace, $after);
            $hunks[] = ['removed' => str_replace("\r\n", "\n", $search), 'added' => str_replace("\r\n", "\n", $replace)];
        }
        if ($after === $before) {
            throw new DisplayException('the edits change nothing.');
        }

        return $this->store($server, $user, $path, (string) ($arguments['summary'] ?? ''), $before, $after, $hunks, $proposed);
    }

    /**
     * @param array<int, AssistantFileChange> $proposed
     *
     * @throws DisplayException
     */
    private function proposeFile(Server $server, User $user, array $arguments, array &$proposed): string
    {
        $path = $this->cleanPath((string) ($arguments['path'] ?? ''));
        $content = (string) ($arguments['content'] ?? '');
        if ($path === '/') {
            throw new DisplayException('give the path of the new file.');
        }
        if (str_contains($content, SecretMask::HIDDEN)) {
            throw new DisplayException('never write (hidden) in a file.');
        }
        $this->checkCount($proposed);
        if ($this->current($server, $path) !== null) {
            throw new DisplayException("$path already exists: read it and use propose_change.");
        }

        return $this->store($server, $user, $path, (string) ($arguments['summary'] ?? ''), null, $content, [['removed' => '', 'added' => $content]], $proposed);
    }

    /**
     * @param array<int, AssistantFileChange> $proposed
     *
     * @throws DisplayException
     */
    private function store(Server $server, User $user, string $path, string $summary, ?string $before, string $after, array $hunks, array &$proposed): string
    {
        if (strlen($after) > self::WRITE_BYTES) {
            throw new DisplayException('the file would be too big.');
        }
        /** @var AssistantFileChange $change */
        $change = AssistantFileChange::query()->create([
            'server_id' => $server->id,
            'user_id' => $user->id,
            'path' => mb_substr($path, 0, 255),
            'summary' => mb_substr(trim($summary), 0, 255),
            'before' => $before,
            'after' => $after,
            'hunks' => json_encode($hunks),
            'state' => AssistantFileChange::PROPOSED,
        ]);
        $proposed[] = $change;

        return "The change of $path is ready (number " . count($proposed) . '). It is NOT applied: the user sees it and clicks Apply. Tell them what it does and that they can put the file back at any time.';
    }

    /**
     * The content of a file now (null when it does not exist).
     */
    private function current(Server $server, string $path): ?string
    {
        try {
            return $this->files->setServer($server)->getContent($path, self::WRITE_BYTES);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<int, AssistantFileChange> $proposed
     *
     * @throws DisplayException
     */
    private function checkCount(array $proposed): void
    {
        if (count($proposed) >= 5) {
            throw new DisplayException('5 changes at most per answer.');
        }
    }

    /**
     * @throws DisplayException
     */
    private function allow(User $user, Server $server, string $permission): void
    {
        if (!$user->can($permission, $server)) {
            throw new DisplayException('you do not have the right to do this on this server (' . $permission . ').');
        }
    }
}
