<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A group of offers in the shop (Minecraft, FiveM, bots...).
 *
 * @property int $id
 * @property string $name
 * @property int $position
 */
class ShopCategory extends Model
{
    protected $table = 'shop_categories';

    protected $guarded = ['id'];

    /**
     * @return HasMany<ShopOffer, $this>
     */
    public function offers(): HasMany
    {
        return $this->hasMany(ShopOffer::class, 'category_id');
    }
}
