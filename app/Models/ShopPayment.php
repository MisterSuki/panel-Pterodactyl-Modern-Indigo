<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money asked from a payment provider to add credit. It is settled once, whatever the number of times the provider or
 * the person comes back (see ShopService::settle).
 *
 * @property int $id
 * @property string $token what identifies it when the person comes back, and what the provider sends back
 * @property int $user_id
 * @property string $provider stripe, paypal or sumup
 * @property string|null $provider_ref
 * @property int $amount_cents
 * @property string $currency
 * @property string $status pending, paid, failed or cancelled
 * @property int|null $buy_offer_id an offer to buy with this money as soon as it arrives
 * @property int|null $renew_order_id an order to renew with this money as soon as it arrives
 * @property string|null $checkout_url
 * @property \Illuminate\Support\Carbon|null $paid_at
 */
class ShopPayment extends Model
{
    public const PENDING = 'pending';

    public const PAID = 'paid';

    public const FAILED = 'failed';

    protected $table = 'shop_payments';

    protected $guarded = ['id'];

    protected $casts = [
        'amount_cents' => 'integer',
        'paid_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
