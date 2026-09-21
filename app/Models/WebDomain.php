<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A name that leads to a site. It is only served once its DNS points to the panel's web server (verified), so that
 * nobody can take over a name that is not theirs.
 *
 * @property int $id
 * @property int $site_id
 * @property string $domain
 * @property bool $include_www
 * @property bool $is_primary
 * @property string $status pending or verified
 * @property \Illuminate\Support\Carbon|null $verified_at
 * @property \Illuminate\Support\Carbon|null $checked_at
 */
class WebDomain extends Model
{
    public const PENDING = 'pending';

    public const VERIFIED = 'verified';

    protected $table = 'web_domains';

    protected $guarded = ['id'];

    protected $casts = [
        'include_www' => 'boolean',
        'is_primary' => 'boolean',
        'verified_at' => 'datetime',
        'checked_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<WebSite, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(WebSite::class, 'site_id');
    }
}
