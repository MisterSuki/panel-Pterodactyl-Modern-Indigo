<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The bill made on the first of a month for the resources a client added to their servers, paid from the credit.
 *
 * @property int $id
 * @property int $user_id
 * @property string $period YYYY-MM
 * @property string $status open, paid or void
 * @property int $total_cents
 * @property \Illuminate\Support\Carbon|null $due_at
 * @property \Illuminate\Support\Carbon|null $paid_at
 */
class ShopInvoice extends Model
{
    public const OPEN = 'open';

    public const PAID = 'paid';

    public const VOID = 'void';

    protected $table = 'shop_invoices';

    protected $guarded = ['id'];

    protected $casts = [
        'total_cents' => 'integer',
        'due_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<ShopInvoiceLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(ShopInvoiceLine::class, 'invoice_id');
    }

    public function isOverdue(): bool
    {
        return $this->status === self::OPEN && $this->due_at !== null && $this->due_at->isPast();
    }
}
