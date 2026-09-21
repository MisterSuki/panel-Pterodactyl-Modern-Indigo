<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An offer bought by a person. The price and the duration are copied here, so changing an offer later does not change
 * what people already bought.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $offer_id
 * @property string $offer_name
 * @property int|null $server_id
 * @property string $status provisioning, active, expired or failed
 * @property int $price_cents
 * @property int $duration_days
 * @property int $renewals
 * @property \Illuminate\Support\Carbon|null $expires_at
 */
class ShopOrder extends Model
{
    public const PROVISIONING = 'provisioning';

    public const ACTIVE = 'active';

    public const EXPIRED = 'expired';

    public const FAILED = 'failed';

    protected $table = 'shop_orders';

    protected $guarded = ['id'];

    protected $casts = [
        'price_cents' => 'integer',
        'duration_days' => 'integer',
        'renewals' => 'integer',
        'expires_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Server, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class)->without('allocation');
    }

    /**
     * @return BelongsTo<ShopOffer, $this>
     */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(ShopOffer::class);
    }
}
