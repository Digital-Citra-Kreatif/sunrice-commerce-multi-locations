<?php

declare(strict_types=1);

namespace Sunrice\CommerceMultiLocations\Pricing;

use Sunrice\Commerce\Checkout\CheckoutRefused;
use Sunrice\Commerce\Orders\Models\Order;
use Sunrice\Commerce\Pricing\CalculationState;
use Sunrice\Commerce\Pricing\Contracts\DiscountProvider;
use Sunrice\Commerce\Pricing\Contracts\ReservesDiscounts;
use Sunrice\CommerceMultiLocations\Fulfilment;

/**
 * A delivery leaves from one location: an order whose lines an extension
 * placed at several (rentals from two locations) can be picked up, not
 * delivered. Stock-only carts are checked when their stock is taken
 * (LocationInventory). It gives no discount: apply() is empty.
 */
class OneLocationPerDelivery implements DiscountProvider, ReservesDiscounts
{
    public function __construct(protected Fulfilment $fulfilment) {}

    public function priority(): int
    {
        return 0;
    }

    public function apply(CalculationState $state): void {}

    public function commit(Order $order, CalculationState $state): void
    {
        if (! commerce_feature('locations') || $order->shipping_address === null || ! config('commerce-multi-locations.one_location_per_delivery', true)) {
            return;
        }
        if (count($this->fulfilment->named($state) ?? []) > 1) {
            throw new CheckoutRefused(__('commerce-multi-locations::locations.errors.one_location'));
        }
    }

    public function release(Order $order): void {}
}
