<?php

declare(strict_types=1);

namespace Sunrice\CommerceMultiLocations\Actions;

use Illuminate\Validation\ValidationException;
use Sunrice\Commerce\Catalog\Models\Variant;
use Sunrice\Commerce\Support\ActivityRecorder;
use Sunrice\CommerceMultiLocations\Inventory\LocationInventory;
use Sunrice\CommerceMultiLocations\Inventory\NotEnoughAtLocation;
use Sunrice\CommerceMultiLocations\Models\Location;
use Sunrice\CommerceMultiLocations\Models\Transfer;

/** Staff move stock of a variant from one location to another. */
class TransferStock
{
    public function __construct(protected LocationInventory $inventory) {}

    public function handle(Variant $variant, Location $from, Location $to, int $quantity, ?int $userId = null, ?string $note = null): Transfer
    {
        if (! $variant->track_stock) {
            throw ValidationException::withMessages(['quantity' => __('commerce-multi-locations::locations.stock.not_tracked')]);
        }
        if ($from->id === $to->id) {
            throw ValidationException::withMessages(['to' => __('commerce-multi-locations::locations.stock.same_location')]);
        }
        if ($quantity < 1) {
            throw ValidationException::withMessages(['quantity' => 'Move at least 1.']);
        }
        try {
            $transfer = $this->inventory->transfer($variant, $from, $to, $quantity, $userId, $note);
        } catch (NotEnoughAtLocation $e) {
            throw ValidationException::withMessages(['quantity' => __('commerce-multi-locations::locations.stock.not_enough', ['location' => $from->name, 'available' => $e->available])]);
        }
        app(ActivityRecorder::class)->record('transferred', 'Stock: '.($variant->sku ?: "variant {$variant->id}"),
            ['note' => "{$quantity}: {$from->name} → {$to->name}".($note ? " ({$note})" : '')], 'stock');

        return $transfer;
    }
}
