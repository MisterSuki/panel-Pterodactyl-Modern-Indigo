<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The automatic backups of a server.
 *
 * @property int $id
 * @property int $server_id
 * @property bool $enabled
 * @property int $interval_hours
 * @property int|null $at_hour
 * @property int $keep
 * @property string|null $ignored
 * @property \Illuminate\Support\Carbon|null $next_run_at
 * @property \Illuminate\Support\Carbon|null $last_run_at
 * @property string|null $last_error
 */
class ServerBackupPlan extends Model
{
    protected $table = 'server_backup_plans';

    protected $guarded = [];

    protected $casts = [
        'server_id' => 'integer',
        'enabled' => 'boolean',
        'interval_hours' => 'integer',
        'at_hour' => 'integer',
        'keep' => 'integer',
        'next_run_at' => 'datetime',
        'last_run_at' => 'datetime',
    ];

    public static function forServer(Server $server): ?self
    {
        return static::query()->where('server_id', $server->id)->first();
    }

    /**
     * The name the automatic backups are given. It is how they are told apart from the ones made by hand,
     * so that keeping "the last 3" never deletes a backup somebody made themselves.
     */
    public const NAME_PREFIX = 'Automatic backup';
}
