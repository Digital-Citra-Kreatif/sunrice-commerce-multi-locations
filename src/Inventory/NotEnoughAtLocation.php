<?php

declare(strict_types=1);

namespace Sunrice\CommerceMultiLocations\Inventory;

use Sunrice\Commerce\Catalog\Models\Variant;
use Sunrice\Commerce\Inventory\OutOfStock;
use Sunrice\CommerceMultiLocations\Models\Location;

/**
 * The location an order is picked up at, or the one its delivery leaves
 * from, has too little of a variant, though others may have it. Checkout shows customerMessage().
 */
class NotEnoughAtLocation extends OutOfStock
{
    public function __construct(Variant $variant, int $requested, int $available, public readonly Location $location, public readonly bool $pickup = false)
    {
        parent::__construct($variant, $requested, $available);
    }

    public function customerMessage(): string
    {
        $title = $this->variant->loadMissing('product')->product->entry()?->shownIn()?->title;
        $label = blank($title) ? ($this->variant->sku ?? '') : ($this->variant->title !== '' ? $title.' ('.$this->variant->title.')' : $title);

        return __('commerce-multi-locations::locations.errors.'.($this->pickup ? 'not_at_location' : 'not_together'), [
            'item' => $label, 'location' => $this->location->name, 'available' => $this->available,
        ]);
    }
}
