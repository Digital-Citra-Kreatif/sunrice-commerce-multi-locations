<?php

declare(strict_types=1);

namespace Sunrice\CommerceMultiLocations\Shipping;

use Sunrice\Commerce\Shipping\Models\ShippingMethod;
use Sunrice\Commerce\Shipping\ShippingRate;
use Sunrice\Commerce\Shipping\ShippingRequest;
use Sunrice\Commerce\Shipping\Types\LocalPickup;
use Sunrice\CommerceMultiLocations\Locations;

/**
 * Local pickup at the store's locations (Shop → Locations, marked for
 * pickup): one rate per location, its id in the rate meta, so the order
 * takes its stock there. Without such locations, the method's own list
 * (core's local pickup).
 */
class LocationPickup extends LocalPickup
{
    public function settingsSchema(): array
    {
        return array_map(function (array $field): array {
            if ($field['handle'] !== 'locations') {
                return $field;
            }
            unset($field['required']);
            $field['config']['min'] = 0;
            $field['instructions'] = 'Leave empty to offer the locations marked for pickup (Shop → Locations).';

            return $field;
        }, parent::settingsSchema());
    }

    public function rates(ShippingRequest $request, ShippingMethod $method): array
    {
        $locations = commerce_feature('locations') ? app(Locations::class)->pickup() : collect();
        if ($locations->isEmpty()) {
            return parent::rates($request, $method);
        }
        $rates = [];
        foreach ($locations as $location) {
            $rates[] = ShippingRate::for($method, (int) $method->setting('cost', 0), 'location-'.$location->id, $method->titleFor().': '.$location->name,
                meta: ['pickup' => true, 'location' => $location->pickupDetails()]);
        }

        return $rates;
    }
}
