<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Why a server is suspended, who did it, and until when (empty means until an administrator lifts it).
 *
 * @property int $id
 * @property int $server_id
 * @property string|null $reason
 * @property int|null $suspended_by
 * @property \Illuminate\Support\Carbon|null $suspended_until
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class ServerSuspension extends Model
{
    protected $table = 'server_suspensions';

    protected $guarded = [];

    protected $casts = [
        'server_id' => 'integer',
        'suspended_by' => 'integer',
        'suspended_until' => 'datetime',
    ];

    public function admin(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'suspended_by');
    }

    public static function forServer(Server $server): ?self
    {
        return static::query()->where('server_id', $server->id)->first();
    }

    /**
     * The suspensions of several servers at once, by server id.
     *
     * @param int[] $serverIds
     *
     * @return Collection<int, self>
     */
    public static function forServers(array $serverIds): Collection
    {
        if (empty($serverIds)) {
            return new Collection();
        }

        return static::query()->whereIn('server_id', $serverIds)->get()->keyBy('server_id');
    }

    /**
     * Whether the suspension has an end that is already behind us.
     */
    public function hasExpired(): bool
    {
        return $this->suspended_until !== null && $this->suspended_until->isPast();
    }
}
