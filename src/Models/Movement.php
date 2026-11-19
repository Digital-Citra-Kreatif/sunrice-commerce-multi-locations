<?php

declare(strict_types=1);

namespace Sunrice\CommerceMultiLocations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One change of a location's stock: an order took it, a cancellation gave
 * it back, staff adjusted it or moved it to another location.
 *
 * @property int $id
 * @property int $location_id
 * @property int $variant_id
 * @property int $delta
 * @property int $quantity_after
 * @property string $reason
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property int|null $transfer_id
 * @property int|null $user_id
 * @property string|null $note
 * @property Carbon|null $created_at
 */
class Movement extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'commerce_location_movements';

    protected $guarded = ['id'];

    protected $casts = ['delta' => 'integer', 'quantity_after' => 'integer', 'reference_id' => 'integer'];

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
