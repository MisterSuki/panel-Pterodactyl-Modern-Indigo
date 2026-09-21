<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One site of a web hosting account, and the server of the panel that runs it.
 *
 * @property int $id
 * @property int $account_id
 * @property int|null $server_id
 * @property string $name
 * @property string|null $php_version
 * @property string $status creating, active or failed
 */
class WebSite extends Model
{
    public const CREATING = 'creating';

    public const ACTIVE = 'active';

    public const FAILED = 'failed';

    protected $table = 'web_sites';

    protected $guarded = ['id'];

    /**
     * @return BelongsTo<WebAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(WebAccount::class, 'account_id');
    }

    /**
     * @return BelongsTo<Server, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * @return HasMany<WebDomain, $this>
     */
    public function domains(): HasMany
    {
        return $this->hasMany(WebDomain::class, 'site_id')->orderByDesc('is_primary')->orderBy('domain');
    }
}
