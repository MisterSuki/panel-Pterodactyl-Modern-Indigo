<?php

namespace Pterodactyl\Services\Eggs;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Pterodactyl\Exceptions\DisplayException;

/**
 * The eggs published on https://eggs.pterodactyl.io/, so an administrator can find one by name and add
 * it to a nest without downloading and uploading a file.
 *
 * The site lists its eggs on three pages (games, applications, generic). Each egg has a page that links to
 * its JSON file in the official pterodactyl repositories. Nothing but those two addresses is ever fetched:
 * an egg has to be in the site's list to be imported, and its file has to come from an official repository.
 */
class CommunityEggCatalog
{
    public const SITE = 'https://eggs.pterodactyl.io';

    public const CATEGORIES = ['games', 'applications', 'generic'];

    private const CACHE_KEY = 'community-eggs:catalog';

    private const CACHE_SECONDS = 21600;

    private const MAX_EGG_BYTES = 1000 * 1024;

    private const RAW_URL_PATTERN = '#https://raw\.githubusercontent\.com/pterodactyl/(?:game|application|generic)-eggs/[A-Za-z0-9_./%+~-]+\.json#';

    public function __construct(private CacheRepository $cache)
    {
    }

    /**
     * Finds eggs by name. Every word typed has to appear in the name, the description or the address of the egg,
     * and eggs whose name starts with what was typed come first.
     *
     * @return array<int, array{slug: string, name: string, description: string, category: string}>
     *
     * @throws DisplayException
     */
    public function search(string $query, int $limit = 20): array
    {
        $needle = $this->normalize($query);
        if (strlen($needle) < 2) {
            return [];
        }
        $words = array_values(array_filter(preg_split('/\s+/', Str::lower(trim($query))) ?: []));

        $everything = [];
        $partial = [];
        foreach ($this->catalog() as $egg) {
            $name = $this->normalize($egg['name']);
            $haystack = $name . '|' . $this->normalize($egg['description']) . '|' . $this->normalize($egg['slug']);

            $found = 0;
            foreach ($words as $word) {
                if (str_contains($haystack, $this->normalize($word))) {
                    ++$found;
                }
            }
            $all = $found === count($words) || str_contains($haystack, $needle);
            if ($found === 0 && !$all) {
                continue;
            }

            if ($name === $needle) {
                $score = 100;
            } elseif (str_starts_with($name, $needle)) {
                $score = 80;
            } elseif (str_contains($name, $needle)) {
                $score = 60;
            } else {
                $score = 20 + $found * 5;
            }

            if ($all) {
                $everything[] = [$score, $egg];
            } else {
                $partial[] = [$found * 10 + ($this->matchesName($name, $words) ? 5 : 0), $egg];
            }
        }

        // "minecraft paper" finds no egg with both words, but the "Paper" egg is what was meant.
        $scored = !empty($everything) ? $everything : $partial;

        usort($scored, fn ($a, $b) => $b[0] <=> $a[0] ?: strcasecmp($a[1]['name'], $b[1]['name']));

        return array_map(fn ($row) => $row[1], array_slice($scored, 0, max(1, $limit)));
    }

    /**
     * The list of every egg of the site, kept for a few hours.
     *
     * @return array<int, array{slug: string, name: string, description: string, category: string}>
     *
     * @throws DisplayException
     */
    public function catalog(): array
    {
        $cached = $this->cache->get(self::CACHE_KEY);
        if (is_array($cached) && !empty($cached)) {
            return $cached;
        }

        $eggs = [];
        foreach (self::CATEGORIES as $category) {
            $html = $this->get(self::SITE . '/eggs/' . $category);
            $eggs = array_merge($eggs, $this->parseListing($html));
        }

        if (empty($eggs)) {
            throw new DisplayException('The list of eggs could not be read from eggs.pterodactyl.io.');
        }

        $this->cache->put(self::CACHE_KEY, $eggs, self::CACHE_SECONDS);

        return $eggs;
    }

    /**
     * Downloads the JSON file of an egg of the list.
     *
     * @throws DisplayException
     */
    public function download(string $slug): string
    {
        $egg = $this->find($slug);
        if ($egg === null) {
            throw new DisplayException('That egg is not in the list of eggs.pterodactyl.io.');
        }

        $page = $this->get(self::SITE . '/egg/' . $egg['slug']);
        $url = $this->extractRawUrl($page);
        if ($url === null) {
            throw new DisplayException('The page of this egg does not link to its file. Try again later, or import it by hand.');
        }

        $body = $this->get($url, self::MAX_EGG_BYTES);
        $decoded = json_decode($body, true);
        if (!is_array($decoded) || !isset($decoded['meta']['version'])) {
            throw new DisplayException('The file of this egg is not a valid egg.');
        }

        return $body;
    }

    /**
     * @return array{slug: string, name: string, description: string, category: string}|null
     *
     * @throws DisplayException
     */
    public function find(string $slug): ?array
    {
        if (preg_match('/^(?:games|applications|generic)-[a-z0-9][a-z0-9-]*$/', $slug) !== 1) {
            return null;
        }

        foreach ($this->catalog() as $egg) {
            if ($egg['slug'] === $slug) {
                return $egg;
            }
        }

        return null;
    }

    /**
     * Reads the cards of a listing page.
     *
     * @return array<int, array{slug: string, name: string, description: string, category: string}>
     */
    public function parseListing(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument();
        $document->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new \DOMXPath($document);
        $eggs = [];
        foreach ($xpath->query('//a[starts-with(@href, "/egg/")]') ?: [] as $link) {
            /** @var \DOMElement $link */
            $slug = trim(substr($link->getAttribute('href'), strlen('/egg/')), '/');
            if (preg_match('/^(games|applications|generic)-[a-z0-9][a-z0-9-]*$/', $slug, $m) !== 1) {
                continue;
            }

            $title = $xpath->query('.//h2', $link)->item(0);
            $description = $xpath->query('.//p', $link)->item(0);
            $name = $title ? $this->clean($title->textContent) : '';
            if ($name === '') {
                continue;
            }

            $eggs[$slug] = [
                'slug' => $slug,
                'name' => $name,
                'description' => $description ? $this->clean($description->textContent) : '',
                'category' => $m[1],
            ];
        }

        return array_values($eggs);
    }

    /**
     * The address of the egg's file, as linked from its page. Only files of the official repositories are accepted.
     */
    public function extractRawUrl(string $html): ?string
    {
        return preg_match(self::RAW_URL_PATTERN, $html, $m) === 1 ? html_entity_decode($m[0]) : null;
    }

    /**
     * @throws DisplayException
     */
    private function get(string $url, int $maxBytes = 5 * 1024 * 1024): string
    {
        try {
            $response = Http::timeout(20)
                ->withHeaders(['User-Agent' => 'Pterodactyl-Panel', 'Accept' => '*/*'])
                ->get($url);
        } catch (\Throwable) {
            throw new DisplayException('eggs.pterodactyl.io could not be reached from this server. Try again in a moment.');
        }

        if (!$response->successful()) {
            throw new DisplayException('eggs.pterodactyl.io answered with an error (' . $response->status() . '). Try again later.');
        }

        $body = $response->body();
        if (strlen($body) > $maxBytes) {
            throw new DisplayException('The answer from eggs.pterodactyl.io is larger than expected and was refused.');
        }

        return $body;
    }

    /**
     * Whether the name of an egg contains one of the words typed.
     *
     * @param string[] $words
     */
    private function matchesName(string $name, array $words): bool
    {
        foreach ($words as $word) {
            if (str_contains($name, $this->normalize($word))) {
                return true;
            }
        }

        return false;
    }

    private function clean(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    /**
     * Lower case letters and digits only, so "7 Days-To-Die" and "7days to die" compare equal.
     */
    private function normalize(string $text): string
    {
        return preg_replace('/[^a-z0-9]+/', '', Str::lower(Str::ascii($text))) ?? '';
    }
}
