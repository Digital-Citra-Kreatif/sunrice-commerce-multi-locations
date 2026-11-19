<?php

declare(strict_types=1);

namespace Sunrice\CommerceMultiLocations\Actions;

use Illuminate\Validation\ValidationException;
use Sunrice\Commerce\Catalog\Models\Variant;
use Sunrice\Commerce\Inventory\Actions\AdjustStock;
use Sunrice\Commerce\Support\ActivityRecorder;
use Sunrice\CommerceMultiLocations\Inventory\LocationInventory;
use Sunrice\CommerceMultiLocations\Models\Location;

/** Staff change a variant's stock at one location: recorded as movements and in the activity log. */
class AdjustLocationStock
{
    public function __construct(protected LocationInventory $inventory) {}

    public function handle(Location $location, Variant $variant, int $quantity, bool $set = false, string $reason = 'manual', ?string $note = null, ?int $userId = null): Variant
    {
        if (! in_array($reason, AdjustStock::REASONS, true)) {
            throw ValidationException::withMessages(['reason' => 'Choose a reason.']);
        }
        if (! $variant->track_stock) {
            throw ValidationException::withMessages(['quantity' => __('commerce-multi-locations::locations.stock.not_tracked')]);
        }
        $before = $this->inventory->byLocation($variant)[$location->id] ?? 0;
        if (($set ? $quantity : $before + $quantity) < 0) {
            throw ValidationException::withMessages(['quantity' => __('commerce-multi-locations::locations.stock.below_zero')]);
        }
        $variant = $this->inventory->adjustAt($location, $variant, $quantity, $set, $reason, $note, $userId);
        $after = $this->inventory->byLocation($variant)[$location->id] ?? 0;

        app(ActivityRecorder::class)->record('adjusted', 'Stock: '.($variant->sku ?: "variant {$variant->id}").' at '.$location->name,
            ['changes' => ['stock_quantity'], 'note' => "{$before} → {$after}".($note ? " ({$note})" : '')], 'stock');

        return $variant;
    }
}
