<?php

declare(strict_types=1);

return [
    'errors' => [
        'not_at_location' => 'Only :available of :item are at :location. Choose another pickup location, or order fewer.',
        'not_together' => 'Only :available of :item can be sent together from one location. Pick them up, or order fewer.',
        'one_location' => 'These items are at different locations and can\'t be sent together. Pick them up, or order them separately.',
    ],
    'location' => [
        'has_stock' => 'This location still has stock: move it to another location first.',
    ],
    'stock' => [
        'adjusted' => ':item at :location: :quantity.',
        'transferred' => ':quantity × :item moved from :from to :to.',
        'not_tracked' => 'Stock isn\'t tracked for this variant: switch on "Track stock" in the product first.',
        'not_enough' => ':location has only :available.',
        'same_location' => 'Choose another location.',
        'below_zero' => 'Stock can\'t be set below 0.',
    ],
];
