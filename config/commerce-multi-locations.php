<?php

declare(strict_types=1);

return [
    /*
    | A delivery leaves from one location. When no single location has every
    | item of the cart, checkout refuses the delivery (the customer can pick
    | up, or order fewer). false: the delivery is quoted from the first
    | location and the stock is taken from wherever it is.
    */
    'one_location_per_delivery' => (bool) env('COMMERCE_LOCATIONS_ONE_PER_DELIVERY', true),

    /*
    | The location that holds stock changed without a location (the product
    | editor, the stock screen, a CSV import): its code. Empty: the first
    | active location (Shop → Locations, by order).
    */
    'default_location' => env('COMMERCE_LOCATIONS_DEFAULT'),
];
