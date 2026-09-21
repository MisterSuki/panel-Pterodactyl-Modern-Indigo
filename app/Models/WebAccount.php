<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The web hosting of a client. The limits are copied from the plan when it is given, so changing a plan later does not
 * take anything away from people who already have it.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $plan_id
 * @property string $plan_name
 * @property int $max_sites
 * @property int $max_domains
 * @property string $status active or suspended
 */
class WebAccount extends Model
{
    public const ACTIVE = 'active';

    public const SUSPENDED = 'suspended';

    protected $table = 'web_accounts';

    protected $guarded = ['id'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<WebPlan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(WebPlan::class, 'plan_id');
    }

    /**
     * @return HasMany<WebSite, $this>
     */
    public function sites(): HasMany
    {
        return $this->hasMany(WebSite::class, 'account_id');
    }
}
