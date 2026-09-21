<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Something the shop sells: a server with fixed limits, for a number of days, at a price.
 *
 * @property int $id
 * @property int|null $category_id
 * @property int|null $web_plan_id the web hosting plan that this offer gives, or null for a game server
 * @property string $name
 * @property string|null $description
 * @property int $price_cents
 * @property int $duration_days
 * @property int $egg_id
 * @property int $location_id
 * @property int $memory
 * @property int $disk
 * @property int $cpu
 * @property int $database_limit
 * @property int $allocation_limit
 * @property int $backup_limit
 * @property array<string, string>|null $environment
 * @property int|null $stock
 * @property bool $enabled
 * @property int $position
 */
class ShopOffer extends Model
{
    protected $table = 'shop_offers';

    protected $guarded = ['id'];

    protected $casts = [
        'price_cents' => 'integer',
        'duration_days' => 'integer',
        'environment' => 'array',
        'stock' => 'integer',
        'enabled' => 'boolean',
    ];

    /**
     * @return BelongsTo<WebPlan, $this>
     */
    public function webPlan(): BelongsTo
    {
        return $this->belongsTo(WebPlan::class, 'web_plan_id');
    }

    public function isWeb(): bool
    {
        return $this->web_plan_id !== null;
    }

    /**
     * @return BelongsTo<ShopCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ShopCategory::class, 'category_id');
    }

    /**
     * @return BelongsTo<Egg, $this>
     */
    public function egg(): BelongsTo
    {
        return $this->belongsTo(Egg::class);
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Whether someone can buy it right now.
     */
    public function isAvailable(): bool
    {
        return $this->enabled && ($this->stock === null || $this->stock > 0);
    }
}
