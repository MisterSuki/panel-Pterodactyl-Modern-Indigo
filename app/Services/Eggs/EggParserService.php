<?php

namespace Pterodactyl\Services\Eggs;

use Illuminate\Support\Arr;
use Pterodactyl\Models\Egg;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Pterodactyl\Exceptions\Service\InvalidFileUploadException;

class EggParserService
{
    /**
     * Takes an uploaded file and parses out the egg configuration from within.
     *
     * @throws \JsonException
     * @throws InvalidFileUploadException
     */
    public function handle(UploadedFile $file): array
    {
        if ($file->getError() !== UPLOAD_ERR_OK || !$file->isFile()) {
            throw new InvalidFileUploadException('The selected file is not valid and cannot be imported.');
        }

        $raw = (string) $file->openFile()->fread(max(1, (int) $file->getSize()));
        // A file saved by some Windows editors starts with an invisible "byte order mark" that JSON does not allow.
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;

        try {
            /** @var array $parsed */
            $parsed = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new InvalidFileUploadException('This file is not valid JSON: ' . $exception->getMessage() . '. Open it in a JSON checker to find the mistake.');
        }
        if (!is_array($parsed)) {
            throw new InvalidFileUploadException('This file is not an egg.');
        }

        $version = (string) (Arr::get($parsed, 'meta.version') ?? '');
        if (str_starts_with($version, 'PLCN_')) {
            // An egg exported from Pelican (the successor of Pterodactyl): same idea, a few fields named differently.
            $parsed = $this->fromPelican($parsed);
            $version = 'PTDL_v2';
        }
        if (!in_array($version, ['PTDL_v1', 'PTDL_v2'], true)) {
            throw new InvalidFileUploadException($version === ''
                ? 'This file is not an egg: it has no "meta.version". Export the egg again from its panel.'
                : 'This egg format (' . $version . ') is not known. Pterodactyl eggs (PTDL_v1, PTDL_v2) and Pelican eggs (PLCN_…) can be imported.');
        }
        if (!is_string(Arr::get($parsed, 'name')) || trim((string) Arr::get($parsed, 'name')) === '') {
            throw new InvalidFileUploadException('This egg has no name.');
        }

        $parsed = $this->convertToV2($parsed);
        $parsed['variables'] = $this->cleanVariables($parsed['variables'] ?? []);

        return $parsed;
    }

    /**
     * Turns a Pelican egg into a Pterodactyl one.
     */
    protected function fromPelican(array $parsed): array
    {
        $parsed['meta']['version'] = 'PTDL_v2';
        // Pelican can have several start commands: the first one is used.
        if (!isset($parsed['startup']) && is_array($parsed['startup_commands'] ?? null)) {
            $parsed['startup'] = (string) (array_values($parsed['startup_commands'])[0] ?? '');
        }
        unset($parsed['startup_commands'], $parsed['tags']);

        return $parsed;
    }

    /**
     * Keeps only what a variable of this panel has, in the right types: other panels add their own fields (sort, id,
     * rules as a list…) that the database would refuse.
     */
    protected function cleanVariables(mixed $variables): array
    {
        $clean = [];
        foreach (is_array($variables) ? $variables : [] as $variable) {
            if (!is_array($variable) || empty($variable['env_variable'])) {
                continue;
            }
            $rules = $variable['rules'] ?? '';
            if (is_array($rules)) {
                $rules = implode('|', array_filter(array_map('strval', $rules), fn ($r) => $r !== ''));
            }
            $default = $variable['default_value'] ?? '';
            $clean[] = [
                'name' => mb_substr((string) ($variable['name'] ?? $variable['env_variable']), 0, 191),
                'description' => (string) ($variable['description'] ?? ''),
                'env_variable' => (string) $variable['env_variable'],
                'default_value' => is_bool($default) ? ($default ? '1' : '0') : (string) ($default ?? ''),
                'user_viewable' => (bool) ($variable['user_viewable'] ?? false),
                'user_editable' => (bool) ($variable['user_editable'] ?? false),
                'rules' => (string) $rules,
                'field_type' => 'text',
            ];
        }

        return $clean;
    }

    /**
     * Fills the provided model with the parsed JSON data.
     */
    public function fillFromParsed(Egg $model, array $parsed): Egg
    {
        return $model->forceFill([
            'name' => Arr::get($parsed, 'name'),
            'description' => Arr::get($parsed, 'description'),
            'features' => Arr::get($parsed, 'features'),
            'docker_images' => Arr::get($parsed, 'docker_images'),
            'file_denylist' => Collection::make(Arr::get($parsed, 'file_denylist'))
                ->filter(fn ($value) => !empty($value)),
            'update_url' => Arr::get($parsed, 'meta.update_url'),
            'config_files' => Arr::get($parsed, 'config.files'),
            'config_startup' => Arr::get($parsed, 'config.startup'),
            'config_logs' => Arr::get($parsed, 'config.logs'),
            'config_stop' => Arr::get($parsed, 'config.stop'),
            'startup' => Arr::get($parsed, 'startup'),
            'script_install' => Arr::get($parsed, 'scripts.installation.script'),
            'script_entry' => Arr::get($parsed, 'scripts.installation.entrypoint'),
            'script_container' => Arr::get($parsed, 'scripts.installation.container'),
        ]);
    }

    /**
     * Converts a PTDL_V1 egg into the expected PTDL_V2 egg format. This just handles
     * the "docker_images" field potentially not being present, and not being in the
     * expected "key => value" format.
     */
    protected function convertToV2(array $parsed): array
    {
        if (Arr::get($parsed, 'meta.version') === Egg::EXPORT_VERSION) {
            return $parsed;
        }

        // Maintain backwards compatability for eggs that are still using the old single image
        // string format. New eggs can provide an array of Docker images that can be used.
        if (!isset($parsed['images'])) {
            $images = [Arr::get($parsed, 'image') ?? 'nil'];
        } else {
            $images = $parsed['images'];
        }

        unset($parsed['images'], $parsed['image']);

        $parsed['docker_images'] = [];
        foreach ($images as $image) {
            $parsed['docker_images'][$image] = $image;
        }

        $parsed['variables'] = array_map(function ($value) {
            return array_merge($value, ['field_type' => 'text']);
        }, is_array($parsed['variables'] ?? null) ? $parsed['variables'] : []);

        return $parsed;
    }
}
