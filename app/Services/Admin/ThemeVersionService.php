<?php

namespace Pterodactyl\Services\Admin;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Tells the admin overview whether the installed theme (this repository) is behind the one on GitHub, so a push there
 * shows up here as "an update is available". The version is the commit the files were installed from: the installer
 * writes it to storage/app/theme-version.json, and this compares it with the latest commit on GitHub.
 */
class ThemeVersionService
{
    public const CACHE_KEY = 'theme:latest-commit';

    private const MARKER = 'theme-version.json';

    public function __construct(private CacheRepository $cache)
    {
    }

    public function repo(): string
    {
        return (string) config('pterodactyl.theme.repo');
    }

    public function branch(): string
    {
        return (string) config('pterodactyl.theme.branch');
    }

    public function repoUrl(): string
    {
        return 'https://github.com/' . $this->repo();
    }

    /**
     * What was installed: the commit the files came from, when it was installed. Null when the panel was themed before
     * this was added, or from a local folder (no commit to point to).
     *
     * @return array{sha: string, at: string|null}|null
     */
    public function installed(): ?array
    {
        try {
            $path = storage_path('app/' . self::MARKER);
            if (!is_file($path)) {
                return null;
            }
            $data = json_decode((string) file_get_contents($path), true);
            $sha = is_array($data) ? (string) ($data['sha'] ?? '') : '';
            if (!preg_match('/^[0-9a-f]{7,40}$/', $sha)) {
                return null;
            }

            return ['sha' => $sha, 'at' => isset($data['at']) ? (string) $data['at'] : null];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * The latest commit on GitHub: its id, when it was made and its message. Kept for a while so the page does not call
     * GitHub on every visit (and stays within the API's free limit).
     *
     * @return array{sha: string, at: string|null, message: string}|null
     */
    public function latest(): ?array
    {
        $minutes = (int) config('pterodactyl.theme.cache_time', 30);

        return $this->cache->remember(self::CACHE_KEY, CarbonImmutable::now()->addMinutes(max(1, $minutes)), function () {
            try {
                $response = Http::withHeaders(['Accept' => 'application/vnd.github+json', 'User-Agent' => 'pterodactyl-panel'])
                    ->timeout(5)
                    ->get('https://api.github.com/repos/' . $this->repo() . '/commits/' . $this->branch());

                if (!$response->successful()) {
                    return null;
                }
                $sha = (string) $response->json('sha', '');
                if (!preg_match('/^[0-9a-f]{40}$/', $sha)) {
                    return null;
                }

                return [
                    'sha' => $sha,
                    'at' => $response->json('commit.author.date'),
                    'message' => (string) $response->json('commit.message', ''),
                ];
            } catch (\Throwable) {
                return null;
            }
        });
    }

    /**
     * What the overview shows: whether the check worked, whether it is up to date, and the little bits of text it needs.
     *
     * @return array{known: bool, upToDate: bool, installed: ?string, latest: ?string, latestAt: ?string, message: ?string, repoUrl: string, branch: string}
     */
    public function status(): array
    {
        $installed = $this->installed();
        $latest = $this->latest();
        $known = $installed !== null && $latest !== null;
        // Same commit, or the installed one is the start of the latest (short id): up to date.
        $upToDate = $known && str_starts_with($latest['sha'], $installed['sha']);

        return [
            'known' => $known,
            'upToDate' => $upToDate,
            'installed' => $installed['sha'] ?? null,
            'latest' => $latest['sha'] ?? null,
            'latestAt' => $latest['at'] ?? null,
            'message' => $latest !== null ? strtok((string) $latest['message'], "\n") : null,
            'repoUrl' => $this->repoUrl(),
            'branch' => $this->branch(),
        ];
    }
}
