<?php

namespace Pterodactyl\Services\Files;

use Illuminate\Support\Facades\Cache;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Models\User;
use Pterodactyl\Models\UserFile;
use Pterodactyl\Models\UserFileConversion;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\PhpExecutableFinder;

/**
 * Converts the files of the file hosting with ffmpeg: pictures (PNG, JPG, WebP, GIF), videos (MP4, WebM, GIF, the
 * sound in MP3) and sounds (MP3, OGG, WAV, M4A, FLAC). It runs in the background (p:files:convert), two at a time on
 * the panel and one at a time per person; the result is a new file next to the first one, counted in the space of
 * its owner.
 */
class FileConverter
{
    // How many conversions run at the same time on the panel, and how long one may take.
    public const MAX_RUNNING = 2;
    public const TIMEOUT = 7200;

    // A conversion that has not moved for this long stopped (the panel restarted, the process was killed...).
    private const STALE = 900;

    // GIF made from a video: at most this many seconds of it, so it stays a reasonable size.
    private const GIF_SECONDS = 30;

    /** @var array<string, string> the formats offered, with the type of the file made */
    public const FORMATS = [
        'png' => 'image/png', 'jpg' => 'image/jpeg', 'webp' => 'image/webp', 'gif' => 'image/gif',
        'mp4' => 'video/mp4', 'webm' => 'video/webm',
        'mp3' => 'audio/mpeg', 'ogg' => 'audio/ogg', 'wav' => 'audio/wav', 'm4a' => 'audio/mp4', 'flac' => 'audio/flac',
    ];

    public function __construct(private FileHosting $hosting)
    {
    }

    /**
     * Where ffmpeg is (null when it is not installed on the panel), looked for every few minutes.
     */
    public function ffmpeg(): ?string
    {
        if (!function_exists('proc_open')) {
            return null;
        }
        $found = Cache::remember('file-converter:ffmpeg', 300, fn () => (string) ((new ExecutableFinder())->find('ffmpeg') ?? ''));

        return $found !== '' ? $found : null;
    }

    public function available(): bool
    {
        return $this->ffmpeg() !== null;
    }

    /**
     * The formats a file can be turned into ([] for a file ffmpeg does not read, or when ffmpeg is not there).
     *
     * @return array<int, string>
     */
    public function targets(UserFile $file): array
    {
        if (!$this->available()) {
            return [];
        }
        $mime = $file->mime;
        $list = match (true) {
            $mime === 'image/svg+xml' => [],
            $mime === 'image/gif' => ['mp4', 'webm', 'png'],
            str_starts_with($mime, 'image/') => ['png', 'jpg', 'webp', 'gif'],
            str_starts_with($mime, 'video/') => ['mp4', 'webm', 'gif', 'mp3'],
            str_starts_with($mime, 'audio/') => ['mp3', 'ogg', 'wav', 'm4a', 'flac'],
            default => [],
        };
        $same = ['audio/x-wav' => 'wav', 'audio/x-flac' => 'flac', 'audio/mp3' => 'mp3'][$mime] ?? array_search($mime, self::FORMATS, true);

        return array_values(array_filter($list, fn (string $format) => $format !== $same));
    }

    /**
     * Starts converting a file of the user.
     *
     * @throws DisplayException
     */
    public function start(User $user, UserFile $file, string $format): UserFileConversion
    {
        if (!$this->available()) {
            throw new DisplayException('The converter is not installed on the panel (ffmpeg is missing).');
        }
        if (!in_array($format, $this->targets($file), true)) {
            throw new DisplayException('This file cannot be converted into this format.');
        }
        if (!is_file($this->hosting->path($file))) {
            throw new DisplayException('This file is missing on the panel.');
        }
        $this->forgetStale($user);
        if (UserFileConversion::query()->where('user_id', $user->id)->whereIn('state', [UserFileConversion::QUEUED, UserFileConversion::RUNNING])->exists()) {
            throw new DisplayException('A conversion is already running: wait for it to end.');
        }

        /** @var UserFileConversion $conversion */
        $conversion = UserFileConversion::query()->create([
            'user_id' => $user->id,
            'file_id' => $file->id,
            'name' => mb_substr($file->name, 0, 191),
            'format' => $format,
            'state' => UserFileConversion::QUEUED,
        ]);

        $php = (new PhpExecutableFinder())->find(false) ?: 'php';
        try {
            exec('setsid nohup ' . escapeshellarg($php) . ' ' . escapeshellarg(base_path('artisan')) . ' p:files:convert ' . $conversion->id . ' > /dev/null 2>&1 &');
        } catch (\Throwable) {
            // Seen as stopped after a while.
        }

        return $conversion;
    }

    /**
     * The conversions of the last day of a user, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function recent(User $user): array
    {
        $this->forgetStale($user);

        return UserFileConversion::query()->where('user_id', $user->id)->where('created_at', '>=', now()->subDay())
            ->orderByDesc('id')->limit(10)->get()->map(fn (UserFileConversion $c) => $this->present($c))->all();
    }

    public function present(UserFileConversion $conversion): array
    {
        return [
            'id' => $conversion->id,
            'name' => $conversion->name,
            'format' => $conversion->format,
            'state' => $conversion->state,
            'progress' => $conversion->progress,
            'error' => $conversion->error,
            'result' => $conversion->result_id ? UserFile::query()->whereKey($conversion->result_id)->value('uuid') : null,
            'created_at' => $conversion->created_at?->toAtomString(),
        ];
    }

    /**
     * Does the conversion (the background command): waits for a free place, runs ffmpeg, keeps the result.
     */
    public function run(UserFileConversion $conversion): void
    {
        // Two at a time on the panel: wait for a place (the waiting keeps the conversion alive).
        $waited = 0;
        while ($this->running() >= self::MAX_RUNNING && $waited < 1800) {
            $conversion->touch();
            sleep(5);
            $waited += 5;
        }
        $taken = UserFileConversion::query()->whereKey($conversion->id)->where('state', UserFileConversion::QUEUED)
            ->update(['state' => UserFileConversion::RUNNING, 'progress' => 0, 'updated_at' => now()]);
        if ($taken !== 1) {
            return;
        }

        $directory = storage_path('app/file-conversions');
        if (!is_dir($directory)) {
            @mkdir($directory, 0750, true);
        }
        $output = $directory . '/' . $conversion->id . '.' . $conversion->format;
        $log = $directory . '/' . $conversion->id . '.log';

        try {
            /** @var UserFile|null $source */
            $source = $conversion->file_id ? UserFile::query()->find($conversion->file_id) : null;
            $ffmpeg = $this->ffmpeg();
            if (!$source || !is_file($this->hosting->path($source))) {
                throw new DisplayException('The file was deleted.');
            }
            if (!$ffmpeg) {
                throw new DisplayException('The converter is not installed on the panel (ffmpeg is missing).');
            }

            $input = $this->hosting->path($source);
            $duration = $this->duration($ffmpeg, $input);
            $command = array_merge(
                $this->nice(),
                [$ffmpeg, '-hide_banner', '-nostdin', '-y', '-i', $input],
                $this->arguments($conversion->format, $source->mime),
                ['-progress', 'pipe:1', '-nostats', $output]
            );
            $this->execute($conversion, $command, $log, $duration);

            if (!is_file($output) || filesize($output) < 1) {
                throw new DisplayException('ffmpeg did not make a file.');
            }
            $name = pathinfo($source->name, PATHINFO_FILENAME) . '.' . $conversion->format;
            $user = User::query()->findOrFail($conversion->user_id);
            $result = $this->hosting->adopt($user, $output, $name, (string) $source->folder);

            $conversion->forceFill(['state' => UserFileConversion::DONE, 'progress' => 100, 'result_id' => $result->id, 'error' => null])->save();
        } catch (DisplayException $exception) {
            $this->fail($conversion, $exception->getMessage());
        } catch (\Throwable $exception) {
            report($exception);
            $this->fail($conversion, 'The conversion failed.');
        } finally {
            @unlink($output);
            @unlink($log);
        }
    }

    /**
     * What ffmpeg is given for a format, after the input (the codecs, the size of a GIF...).
     *
     * @return array<int, string>
     */
    public function arguments(string $format, string $mime): array
    {
        $still = str_starts_with($mime, 'image/') && $mime !== 'image/gif';
        $sound = ['-map_metadata', '0'];

        return match ($format) {
            // One picture: a picture turned into another, or the first image of a GIF.
            'png' => ['-frames:v', '1', '-update', '1'],
            'jpg' => ['-frames:v', '1', '-update', '1', '-q:v', '2'],
            'webp' => ['-frames:v', '1', '-c:v', 'libwebp', '-quality', '85'],
            'gif' => $still
                ? ['-frames:v', '1']
                : ['-t', (string) self::GIF_SECONDS, '-vf', "fps=12,scale='min(640,iw)':-2:flags=lanczos,split[a][b];[a]palettegen[p];[b][p]paletteuse", '-loop', '0', '-an'],
            'mp4' => [
                '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '23', '-pix_fmt', 'yuv420p',
                // H.264 wants even sizes (a GIF of 301 pixels wide would fail).
                '-vf', 'scale=trunc(iw/2)*2:trunc(ih/2)*2',
                '-c:a', 'aac', '-b:a', '160k', '-movflags', '+faststart',
            ],
            'webm' => [
                // yuv420p: a GIF with transparency would give a format VP9 refuses.
                '-c:v', 'libvpx-vp9', '-pix_fmt', 'yuv420p', '-b:v', '0', '-crf', '33', '-deadline', 'realtime', '-cpu-used', '8', '-row-mt', '1',
                '-c:a', 'libopus', '-b:a', '128k',
            ],
            'mp3' => array_merge(['-vn', '-c:a', 'libmp3lame', '-q:a', '2'], $sound),
            'ogg' => array_merge(['-vn', '-c:a', 'libvorbis', '-q:a', '5'], $sound),
            'wav' => array_merge(['-vn', '-c:a', 'pcm_s16le'], $sound),
            'm4a' => array_merge(['-vn', '-c:a', 'aac', '-b:a', '192k', '-movflags', '+faststart'], $sound),
            'flac' => array_merge(['-vn', '-c:a', 'flac'], $sound),
            default => throw new DisplayException('This format is not offered.'),
        };
    }

    /**
     * Turns a line of "ffmpeg -progress" into a percentage, from the length of the input.
     */
    public function percent(string $line, float $duration): ?int
    {
        if ($duration <= 0 || !preg_match('/^out_time_(?:us|ms)=(\d+)$/', trim($line), $match)) {
            return null;
        }

        return max(0, min(99, (int) floor((int) $match[1] / 1000000 / $duration * 100)));
    }

    /**
     * Runs ffmpeg (without a shell), following its progress; stopped after TIMEOUT.
     *
     * @param array<int, string> $command
     *
     * @throws DisplayException
     */
    private function execute(UserFileConversion $conversion, array $command, string $log, float $duration): void
    {
        $process = proc_open($command, [0 => ['file', $this->nowhere(), 'r'], 1 => ['pipe', 'w'], 2 => ['file', $log, 'w']], $pipes);
        if (!is_resource($process)) {
            throw new DisplayException('ffmpeg could not be started.');
        }
        stream_set_blocking($pipes[1], false);
        $started = time();
        $saved = 0;
        $buffer = '';

        while (true) {
            $status = proc_get_status($process);
            $chunk = (string) fread($pipes[1], 8192);
            $buffer .= $chunk;
            while (($at = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $at);
                $buffer = substr($buffer, $at + 1);
                $percent = $this->percent($line, $duration);
                if ($percent !== null && time() - $saved >= 2) {
                    $conversion->forceFill(['progress' => $percent])->save();
                    $saved = time();
                }
            }
            // Alive, even when the length is unknown (no percentage to write).
            if (time() - $saved >= 30) {
                $conversion->touch();
                $saved = time();
            }
            if (!$status['running']) {
                break;
            }
            if (time() - $started > self::TIMEOUT) {
                proc_terminate($process, 9);
                fclose($pipes[1]);
                proc_close($process);
                throw new DisplayException('The conversion took too long (more than 2 hours).');
            }
            if ($chunk === '') {
                usleep(200000);
            }
        }
        fclose($pipes[1]);
        proc_close($process);

        if ($status['exitcode'] !== 0) {
            throw new DisplayException($this->reason($log));
        }
    }

    /**
     * The length of a video or a sound in seconds (0 for a picture, or when it cannot be read), from what ffmpeg says
     * about the file.
     */
    private function duration(string $ffmpeg, string $input): float
    {
        $process = proc_open([$ffmpeg, '-hide_banner', '-nostdin', '-i', $input], [0 => ['file', $this->nowhere(), 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            return 0;
        }
        $text = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        return preg_match('/Duration:\s*(\d+):(\d+):(\d+(?:\.\d+)?)/', $text, $m) ? (int) $m[1] * 3600 + (int) $m[2] * 60 + (float) $m[3] : 0;
    }

    /**
     * The last thing ffmpeg said before it failed, short.
     */
    private function reason(string $log): string
    {
        $lines = [];
        foreach (preg_split('/\r\n|\r|\n/', (string) @file_get_contents($log)) ?: [] as $line) {
            // Without the "[libvpx-vp9 @ 0x...]" in front.
            $line = trim((string) preg_replace('/^(\[[^\]]*\]\s*)+/', '', $line));
            if ($line !== '' && !preg_match('/^Conversion failed/i', $line)) {
                $lines[] = $line;
            }
        }
        $useful = array_values(array_filter($lines, fn ($l) => preg_match('/error|invalid|not supported|no such|unknown|could not|does not contain/i', $l) === 1));
        $last = end($useful) ?: (end($lines) ?: '');

        return 'ffmpeg could not convert this file' . ($last !== '' ? ': ' . mb_substr($last, 0, 140) : '.');
    }

    /**
     * Runs ffmpeg with a low priority, so the panel stays fast during a conversion.
     *
     * @return array<int, string>
     */
    private function nice(): array
    {
        $nice = (new ExecutableFinder())->find('nice');

        return $nice ? [$nice, '-n', '10'] : [];
    }

    private function nowhere(): string
    {
        return PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
    }

    private function running(): int
    {
        return UserFileConversion::query()->where('state', UserFileConversion::RUNNING)
            ->where('updated_at', '>=', now()->subSeconds(self::STALE))->count();
    }

    private function fail(UserFileConversion $conversion, string $message): void
    {
        $conversion->forceFill(['state' => UserFileConversion::FAILED, 'error' => mb_substr($message, 0, 191)])->save();
    }

    /**
     * Conversions that stopped moving are marked as failed (they no longer block a new one).
     */
    private function forgetStale(User $user): void
    {
        UserFileConversion::query()->where('user_id', $user->id)
            ->whereIn('state', [UserFileConversion::QUEUED, UserFileConversion::RUNNING])
            ->where('updated_at', '<', now()->subSeconds(self::STALE))
            ->update(['state' => UserFileConversion::FAILED, 'error' => 'The conversion stopped.']);
    }
}
