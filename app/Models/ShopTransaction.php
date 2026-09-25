<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One movement of the credit of a person: money that came in (topup), an offer bought or renewed (purchase, renewal),
 * money given back (refund) or a correction made by the staff (adjust). A positive amount adds credit, a negative one
 * takes some away.
 *
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property int $amount_cents
 * @property int $balance_after_cents
 * @property string|null $note
 * @property int|null $payment_id
 * @property int|null $order_id
 * @property int|null $staff_id
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class ShopTransaction extends Model
{
    public $timestamps = false;

    protected $table = 'shop_transactions';

    protected $guarded = ['id'];

    protected $casts = [
        'amount_cents' => 'integer',
        'balance_after_cents' => 'integer',
        'created_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
