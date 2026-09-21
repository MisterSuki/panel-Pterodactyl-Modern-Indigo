<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * What a client of the web hosting can have: how many sites and domains, what each site gets, and which PHP versions
 * it can use (each one is the docker image that runs it).
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int $egg_id
 * @property int $location_id
 * @property int $max_sites
 * @property int $max_domains
 * @property int $memory
 * @property int $disk
 * @property int $cpu
 * @property int $database_limit
 * @property int $backup_limit
 * @property array<string, string>|null $php_versions version => docker image
 * @property string|null $default_php
 * @property array<string, string>|null $environment
 * @property bool $enabled
 * @property int $position
 */
class WebPlan extends Model
{
    protected $table = 'web_plans';

    protected $guarded = ['id'];

    protected $casts = [
        'php_versions' => 'array',
        'environment' => 'array',
        'enabled' => 'boolean',
    ];

    /**
     * The versions of PHP that a site of this plan can use.
     *
     * @return array<int, string>
     */
    public function versions(): array
    {
        return array_keys($this->php_versions ?? []);
    }

    /**
     * The docker image of a version, or null if the plan does not have it.
     */
    public function imageFor(?string $version): ?string
    {
        return $version !== null ? ($this->php_versions[$version] ?? null) : null;
    }

    /**
     * The version a new site starts with: the one that was chosen, or the first one.
     */
    public function startingVersion(): ?string
    {
        $versions = $this->versions();
        if ($this->default_php && in_array($this->default_php, $versions, true)) {
            return $this->default_php;
        }

        return $versions[0] ?? null;
    }
}
