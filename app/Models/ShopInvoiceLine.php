<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of an invoice: what it is for and how much.
 *
 * @property int $id
 * @property int $invoice_id
 * @property int|null $order_id
 * @property string $label
 * @property int $amount_cents
 */
class ShopInvoiceLine extends Model
{
    protected $table = 'shop_invoice_lines';

    protected $guarded = ['id'];

    protected $casts = [
        'amount_cents' => 'integer',
    ];

    /**
     * @return BelongsTo<ShopInvoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(ShopInvoice::class, 'invoice_id');
    }
}
