<?php

declare(strict_types=1);

namespace Sunrice\CommerceMultiLocations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Sunrice\Commerce\Catalog\Models\Variant;

/**
 * How many of a variant a location has. Changed only through
 * LocationInventory, which keeps the variant's stock_quantity the sum.
 *
 * @property int $id
 * @property int $location_id
 * @property int $variant_id
 * @property int $quantity
 * @property-read Location $location
 * @property-read Variant $variant
 */
class LocationStock extends Model
{
    protected $table = 'commerce_location_stock';

    protected $guarded = ['id'];

    protected $casts = ['location_id' => 'integer', 'variant_id' => 'integer', 'quantity' => 'integer'];

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return BelongsTo<Variant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class);
    }

    /** @return HasMany<Movement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(Movement::class, 'variant_id', 'variant_id')->where('location_id', $this->location_id)->latest('id');
    }
}
