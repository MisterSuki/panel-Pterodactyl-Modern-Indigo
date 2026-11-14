<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A maintenance shown on the public status page: planned, in progress or done. While it runs, what it concerns shows
 * "Maintenance" instead of a failure, and its time does not count against the uptime.
 *
 * @property int $id
 * @property string $title
 * @property string|null $body
 * @property \Illuminate\Support\Carbon $starts_at
 * @property \Illuminate\Support\Carbon $ends_at
 * @property array $components "panel", "node:<id>", "proxmox:<id of the host>"
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class StatusMaintenance extends Model
{
    protected $table = 'status_maintenances';

    protected $fillable = ['title', 'body', 'starts_at', 'ends_at', 'components'];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'components' => 'array'];

    public function isActive(): bool
    {
        return $this->starts_at->lte(now()) && $this->ends_at->gt(now());
    }

    public function isUpcoming(): bool
    {
        return $this->starts_at->gt(now());
    }

    public function covers(string $component): bool
    {
        return in_array($component, (array) $this->components, true);
    }
}
