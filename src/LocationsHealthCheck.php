<?php

declare(strict_types=1);

namespace Sunrice\CommerceMultiLocations;

use Illuminate\Support\Facades\DB;
use Sunrice\Commerce\Health\HealthCheck;
use Sunrice\Commerce\Health\HealthResult;
use Sunrice\CommerceMultiLocations\Models\Location;

/** Shop → Health: locations to ship from and pick up at, and stock that adds up. */
class LocationsHealthCheck implements HealthCheck
{
    public function key(): string
    {
        return 'locations';
    }

    public function label(): string
    {
        return 'Locations';
    }

    public function run(): HealthResult
    {
        if (! commerce_feature('locations')) {
            return HealthResult::ok('Locations are switched off.');
        }
        $locations = app(Locations::class);
        if ($locations->active()->isEmpty()) {
            return HealthResult::warning('No active location: stock is counted for the whole store and deliveries leave from the store address.', 'Add your warehouses and shops in Shop → Locations.');
        }
        $unplaced = $locations->shipping()->filter(fn (Location $l) => blank($l->postal_code) && blank($l->village_code) && ($l->latitude === null || $l->longitude === null));
        if ($unplaced->isNotEmpty()) {
            return HealthResult::error('Couriers can\'t quote from '.$unplaced->pluck('name')->implode(', ').': no postal code or map point.', 'Add the postal code (or the map point) in Shop → Locations.');
        }
        $mismatched = DB::table('commerce_variants as v')->where('v.track_stock', true)
            ->whereExists(fn ($q) => $q->from('commerce_location_stock as s')->whereColumn('s.variant_id', 'v.id'))
            ->whereRaw('COALESCE(v.stock_quantity, 0) <> (SELECT COALESCE(SUM(s.quantity), 0) FROM commerce_location_stock s WHERE s.variant_id = v.id)')
            ->count();
        if ($mismatched > 0) {
            return HealthResult::warning("{$mismatched} variants' stock differs from what their locations hold; it goes to the default location with their next change.");
        }

        return HealthResult::ok($locations->active()->count().' active locations.');
    }
}
