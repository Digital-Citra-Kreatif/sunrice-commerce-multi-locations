<?php

declare(strict_types=1);

namespace Sunrice\CommerceMultiLocations\Console;

use Illuminate\Console\Command;
use Sunrice\Commerce\Catalog\Models\Variant;
use Sunrice\CommerceMultiLocations\Inventory\LocationInventory;
use Sunrice\CommerceMultiLocations\Locations;

/**
 * Puts every tracked variant's stock that is at no location yet (from before
 * the package, or a new variant's initial stock) at the default location.
 * Safe to run again: it only moves the difference.
 */
class PlaceStockCommand extends Command
{
    protected $signature = 'locations:place-stock';

    protected $description = 'Put stock that is at no location yet at the default location';

    public function handle(LocationInventory $inventory, Locations $locations): int
    {
        $default = $locations->default();
        if ($default === null) {
            $this->error('Add a location first (Locations → Locations).');

            return self::FAILURE;
        }
        $count = 0;
        Variant::query()->where('track_stock', true)->orderBy('id')->chunkById(200, function ($variants) use ($inventory, &$count): void {
            foreach ($variants as $variant) {
                $inventory->byLocation($variant);
                $count++;
            }
        });
        $this->info("Checked {$count} variants; stock at no location is now at {$default->name}.");

        return self::SUCCESS;
    }
}
