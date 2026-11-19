<?php

declare(strict_types=1);

namespace Sunrice\CommerceMultiLocations\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Sunrice\Commerce\Catalog\Models\Variant;
use Sunrice\Commerce\Inventory\DatabaseInventory;
use Sunrice\Commerce\Support\Staff;
use Sunrice\CommerceMultiLocations\Fulfilment;
use Sunrice\CommerceMultiLocations\Locations;
use Sunrice\CommerceMultiLocations\Models\Location;
use Sunrice\CommerceMultiLocations\Models\LocationStock;
use Sunrice\CommerceMultiLocations\Models\Movement;
use Sunrice\CommerceMultiLocations\Models\Transfer;

/**
 * Stock per location (commerce_location_stock), with the variant's
 * stock_quantity kept as the sum so everything that reads it (listings,
 * feeds, reports, low-stock alerts) keeps working.
 *
 * An order takes its stock from the location it is picked up at or
 * delivered from (Fulfilment::forReference()), else from the locations in
 * order. A cancellation or refund gives it back where it came from. A change
 * without a location (the product editor, the stock screen, an import) is
 * made at the default location; adjustAt() and transfer() name one.
 *
 * Every change first takes up any difference between the sum and the
 * variant's stock_quantity at the default location (reconcile()): stock
 * from before the package was installed, or a new variant's initial stock.
 */
class LocationInventory extends DatabaseInventory
{
    public function __construct(protected Locations $locations, protected Fulfilment $fulfilment) {}

    public function reserve(array $lines, ?Model $reference = null, string $reason = 'order'): void
    {
        if (! $this->locations->any()) {
            parent::reserve($lines, $reference, $reason);

            return;
        }
        DB::transaction(function () use ($lines, $reference, $reason): void {
            $at = $this->fulfilment->forReference($reference);
            // Picked up there, or delivered from there with one location per delivery: it all has to be there.
            $strict = $at !== null && (Fulfilment::isPickup($reference) || (bool) config('commerce-multi-locations.one_location_per_delivery', true));
            $quantities = $this->explode($lines);
            ksort($quantities);
            foreach ($quantities as [$variant, $qty]) {
                if ($variant->track_stock) {
                    $this->take($variant, $qty, $at, $strict, $reference, $reason);
                }
            }
            parent::reserve($lines, $reference, $reason);
        });
    }

    public function release(array $lines, ?Model $reference = null, string $reason = 'cancel'): void
    {
        if (! $this->locations->any()) {
            parent::release($lines, $reference, $reason);

            return;
        }
        DB::transaction(function () use ($lines, $reference, $reason): void {
            foreach ($this->explode($lines) as [$variant, $qty]) {
                if ($variant->track_stock) {
                    $this->giveBack($variant, $qty, $reference, $reason);
                }
            }
            parent::release($lines, $reference, $reason);
        });
    }

    public function adjust(Variant $variant, int $quantity, bool $set = false, string $reason = 'manual', ?string $note = null, ?int $userId = null): Variant
    {
        $location = $this->locations->default();
        if ($location === null || ! $variant->track_stock) {
            return parent::adjust($variant, $quantity, $set, $reason, $note, $userId);
        }

        return DB::transaction(function () use ($variant, $quantity, $set, $reason, $note, $userId, $location): Variant {
            $rows = $this->rows($variant);
            $delta = $set ? $quantity - (int) $rows->sum('quantity') : $quantity;
            if ($delta >= 0) {
                $this->change($this->row($rows, $location, $variant), $delta, $reason, null, $note, $userId);
            } else {
                // Less: from the default location first, then the others in order.
                $this->takeInOrder($variant, $rows, -$delta, $location, $reason, null, $note, $userId);
            }

            return parent::adjust($variant, $delta, false, $reason, $note, $userId);
        });
    }

    /** A manual change at one location: by $quantity, or to $quantity when $set. */
    public function adjustAt(Location $location, Variant $variant, int $quantity, bool $set = false, string $reason = 'manual', ?string $note = null, ?int $userId = null): Variant
    {
        if (! $variant->track_stock) {
            throw new InvalidArgumentException("Stock isn't tracked for variant {$variant->id}.");
        }

        return DB::transaction(function () use ($location, $variant, $quantity, $set, $reason, $note, $userId): Variant {
            $row = $this->row($this->rows($variant), $location, $variant);
            $delta = $set ? $quantity - $row->quantity : $quantity;
            $this->change($row, $delta, $reason, null, $note, $userId);

            return parent::adjust($variant, $delta, false, $reason, $note, $userId);
        });
    }

    /**
     * Moves stock between locations; the variant's total stays the same.
     *
     * @throws NotEnoughAtLocation
     */
    public function transfer(Variant $variant, Location $from, Location $to, int $quantity, ?int $userId = null, ?string $note = null): Transfer
    {
        if ($quantity < 1 || $from->id === $to->id || ! $variant->track_stock) {
            throw new InvalidArgumentException('A transfer moves at least one tracked item to another location.');
        }

        return DB::transaction(function () use ($variant, $from, $to, $quantity, $userId, $note): Transfer {
            $rows = $this->rows($variant);
            $source = $this->row($rows, $from, $variant);
            if ($source->quantity < $quantity) {
                throw new NotEnoughAtLocation($variant, $quantity, max(0, $source->quantity), $from);
            }
            $userId ??= auth(Staff::guard())->id();
            $transfer = Transfer::query()->create(['from_location_id' => $from->id, 'to_location_id' => $to->id, 'variant_id' => $variant->id,
                'quantity' => $quantity, 'user_id' => $userId, 'note' => $note !== null && $note !== '' ? $note : null, 'created_at' => now()]);
            $this->change($source, -$quantity, 'transfer', null, $note, $userId, $transfer->id);
            $this->change($this->row($rows, $to, $variant), $quantity, 'transfer', null, $note, $userId, $transfer->id);

            return $transfer;
        });
    }

    /** @return array<int, int> location id => quantity, for this variant (after reconcile()) */
    public function byLocation(Variant $variant): array
    {
        return DB::transaction(fn () => $this->rows($variant)->pluck('quantity', 'location_id')->map(fn ($q) => (int) $q)->all());
    }

    /**
     * Takes $qty of the variant: all at $at when $strict, else from $at
     * first and then the other locations in order.
     */
    protected function take(Variant $variant, int $qty, ?Location $at, bool $strict, ?Model $reference, string $reason): void
    {
        $rows = $this->rows($variant);
        if ($strict && $at !== null) {
            $row = $this->row($rows, $at, $variant);
            if ($row->quantity < $qty && $variant->backorders === 'no') {
                throw new NotEnoughAtLocation($variant, $qty, max(0, $row->quantity), $at, Fulfilment::isPickup($reference));
            }
            $this->change($row, -$qty, $reason, $reference);

            return;
        }
        $this->takeInOrder($variant, $rows, $qty, $at, $reason, $reference);
    }

    /**
     * Takes $qty from $first (if given) and then the locations in order;
     * what no location has comes from the first, below zero, when the
     * variant allows backorders (parent::reserve() refuses it otherwise).
     *
     * @param  Collection<int, LocationStock>  $rows
     */
    protected function takeInOrder(Variant $variant, Collection $rows, int $qty, ?Location $first, string $reason, ?Model $reference = null, ?string $note = null, ?int $userId = null): void
    {
        $order = $this->locations->all()->sortBy(fn (Location $l) => $l->id === $first?->id ? -1 : 0, SORT_REGULAR)->values();
        $left = $qty;
        foreach ($order as $location) {
            if ($left === 0) {
                break;
            }
            $row = $rows->firstWhere('location_id', $location->id);
            $taken = $row === null ? 0 : min($left, max(0, $row->quantity));
            if ($taken > 0) {
                $this->change($row, -$taken, $reason, $reference, $note, $userId);
                $left -= $taken;
            }
        }
        if ($left > 0) {
            $location = $first ?? $this->locations->default();
            if ($location !== null) {
                $this->change($this->row($rows, $location, $variant), -$left, $reason, $reference, $note, $userId);
            }
        }
    }

    /** Gives $qty back to the locations the reference took it from, the rest to the default location. */
    protected function giveBack(Variant $variant, int $qty, ?Model $reference, string $reason): void
    {
        $rows = $this->rows($variant);
        $left = $qty;
        if ($reference !== null) {
            $taken = Movement::query()->where('variant_id', $variant->id)
                ->where('reference_type', $reference->getMorphClass())->where('reference_id', $reference->getKey())
                ->groupBy('location_id')->selectRaw('location_id, -SUM(delta) as net')->pluck('net', 'location_id');
            foreach ($taken as $locationId => $net) {
                $location = $this->locations->find((int) $locationId);
                $back = min($left, (int) $net);
                if ($location !== null && $back > 0) {
                    $this->change($this->row($rows, $location, $variant), $back, $reason, $reference);
                    $left -= $back;
                }
            }
        }
        $default = $this->locations->default();
        if ($left > 0 && $default !== null) {
            $this->change($this->row($rows, $default, $variant), $left, $reason, $reference);
        }
    }

    /**
     * The variant's rows, locked, after taking up any difference with its
     * stock_quantity at the default location.
     *
     * @return Collection<int, LocationStock>
     */
    protected function rows(Variant $variant): Collection
    {
        DB::table('commerce_variants')->where('id', $variant->id)->lockForUpdate()->value('id');
        /** @var Collection<int, LocationStock> $rows */
        $rows = LocationStock::query()->where('variant_id', $variant->id)->lockForUpdate()->get();
        $total = (int) DB::table('commerce_variants')->where('id', $variant->id)->value('stock_quantity');
        $difference = $total - (int) $rows->sum('quantity');
        $default = $this->locations->default();
        if ($difference !== 0 && $default !== null) {
            $this->change($this->row($rows, $default, $variant), $difference, 'reconcile', null, 'Stock not yet at a location.');
        }

        return $rows;
    }

    /**
     * The variant's row at the location, made (at 0) when missing.
     *
     * @param  Collection<int, LocationStock>  $rows
     */
    protected function row(Collection $rows, Location $location, Variant $variant): LocationStock
    {
        $row = $rows->firstWhere('location_id', $location->id);
        if ($row === null) {
            $row = LocationStock::query()->create(['location_id' => $location->id, 'variant_id' => $variant->id, 'quantity' => 0]);
            $rows->push($row);
        }

        return $row;
    }

    protected function change(LocationStock $row, int $delta, string $reason, ?Model $reference = null, ?string $note = null, ?int $userId = null, ?int $transferId = null): void
    {
        if ($delta === 0) {
            return;
        }
        LocationStock::query()->whereKey($row->id)->update(['quantity' => DB::raw('quantity + '.$delta), 'updated_at' => now()]);
        $row->quantity += $delta;
        $row->syncOriginalAttribute('quantity');
        Movement::query()->create([
            'location_id' => $row->location_id, 'variant_id' => $row->variant_id, 'delta' => $delta, 'quantity_after' => $row->quantity,
            'reason' => $reason, 'reference_type' => $reference?->getMorphClass(), 'reference_id' => $reference?->getKey(),
            'transfer_id' => $transferId, 'user_id' => $userId ?? auth(Staff::guard())->id(),
            'note' => $note !== null && $note !== '' ? mb_substr($note, 0, 500) : null,
        ]);
    }
}
