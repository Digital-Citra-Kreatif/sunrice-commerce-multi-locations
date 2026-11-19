<?php

declare(strict_types=1);

namespace Sunrice\CommerceMultiLocations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stock of a variant moved from one location to another.
 *
 * @property int $id
 * @property int $from_location_id
 * @property int $to_location_id
 * @property int $variant_id
 * @property int $quantity
 * @property int|null $user_id
 * @property string|null $note
 * @property-read Location $from
 * @property-read Location $to
 */
class Transfer extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'commerce_location_transfers';

    protected $guarded = ['id'];

    protected $casts = ['quantity' => 'integer'];

    /** @return BelongsTo<Location, $this> */
    public function from(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    /** @return BelongsTo<Location, $this> */
    public function to(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }
}
