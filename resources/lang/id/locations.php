<?php

declare(strict_types=1);

return [
    'errors' => [
        'not_at_location' => 'Hanya ada :available :item di :location. Pilih lokasi ambil lain, atau kurangi jumlahnya.',
        'not_together' => 'Hanya :available :item yang bisa dikirim bersamaan dari satu lokasi. Ambil di toko, atau kurangi jumlahnya.',
        'one_location' => 'Barang-barang ini ada di lokasi yang berbeda dan tidak bisa dikirim bersamaan. Ambil di toko, atau pesan terpisah.',
    ],
    'location' => [
        'has_stock' => 'Lokasi ini masih punya stok: pindahkan dulu ke lokasi lain.',
    ],
    'stock' => [
        'adjusted' => ':item di :location: :quantity.',
        'transferred' => ':quantity × :item dipindah dari :from ke :to.',
        'not_tracked' => 'Stok varian ini tidak dihitung: nyalakan "Lacak stok" di produk dulu.',
        'not_enough' => 'Di :location hanya ada :available.',
        'same_location' => 'Pilih lokasi lain.',
        'below_zero' => 'Stok tidak bisa kurang dari 0.',
    ],
];
