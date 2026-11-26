<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The state of a website for the monitor: up or down, how fast it answers, since when it is down, its certificate.
 *
 * @property int $server_id
 * @property string|null $url
 * @property string $state up, down, stopped, no_domain or unknown
 * @property int|null $status_code
 * @property int|null $response_ms
 * @property int $failures checks failed in a row
 * @property \Illuminate\Support\Carbon|null $down_since
 * @property bool $alerted the alert "down" was sent
 * @property string|null $last_error
 * @property \Illuminate\Support\Carbon|null $last_checked_at
 * @property \Illuminate\Support\Carbon|null $ssl_expires_at
 * @property \Illuminate\Support\Carbon|null $ssl_checked_at
 * @property \Illuminate\Support\Carbon|null $ssl_warned_at
 */
class WebsiteMonitor extends Model
{
    protected $table = 'website_monitors';

    protected $primaryKey = 'server_id';

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'server_id' => 'integer',
        'status_code' => 'integer',
        'response_ms' => 'integer',
        'failures' => 'integer',
        'alerted' => 'boolean',
        'down_since' => 'datetime',
        'last_checked_at' => 'datetime',
        'ssl_expires_at' => 'datetime',
        'ssl_checked_at' => 'datetime',
        'ssl_warned_at' => 'datetime',
    ];
}
