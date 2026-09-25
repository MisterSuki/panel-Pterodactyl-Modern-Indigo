<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A change of resources made in the middle of a month, with the part of the month left to pay for it. It waits until the
 * first of the next month, when it is put on that month's invoice.
 *
 * @property int $id
 * @property int $user_id
 * @property int $order_id
 * @property string $description
 * @property int $amount_cents the proration (can be negative when going back down)
 * @property int|null $invoice_id set once it has been billed
 */
class ShopResourceChange extends Model
{
    protected $table = 'shop_resource_changes';

    protected $guarded = ['id'];

    protected $casts = [
        'amount_cents' => 'integer',
    ];

    /**
     * @return BelongsTo<ShopOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(ShopOrder::class, 'order_id');
    }
}
