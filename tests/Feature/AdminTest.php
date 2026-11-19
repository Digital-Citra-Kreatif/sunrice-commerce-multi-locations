<?php

declare(strict_types=1);

use Sunrice\CommerceMultiLocations\LocationsHealthCheck;
use Sunrice\CommerceMultiLocations\Models\Location;
use Sunrice\CommerceMultiLocations\Models\LocationStock;

beforeEach(function () {
    $this->mug = mugStore(10);
    [$this->warehouse, $this->shop] = twoLocations();
    $this->artisan('locations:place-stock');
    actingAsSuperAdmin();
});

it('lists and edits locations', function () {
    $this->get('/admin/locations/locations')->assertOk();
    $this->get("/admin/locations/locations/{$this->shop->id}/edit")->assertOk();
});

it('refuses to delete a location that still has stock', function () {
    $this->delete("/admin/locations/locations/{$this->warehouse->id}")->assertSessionHas('error', __('commerce-multi-locations::locations.location.has_stock'));

    expect(Location::query()->whereKey($this->warehouse->id)->exists())->toBeTrue();
});

it('adjusts and transfers stock from the stock list', function () {
    $row = LocationStock::query()->where('location_id', $this->warehouse->id)->firstOrFail();
    $this->get('/admin/locations/stock')->assertOk();

    $this->post("/admin/locations/stock/{$row->id}/actions/adjust", ['mode' => 'add', 'quantity' => 5, 'reason' => 'manual', 'note' => 'Supplier'])
        ->assertSessionHasNoErrors();
    $this->post("/admin/locations/stock/{$row->id}/actions/transfer", ['to' => $this->shop->id, 'quantity' => 6])
        ->assertSessionHasNoErrors();
    $this->post("/admin/locations/stock/{$row->id}/actions/transfer", ['to' => $this->shop->id, 'quantity' => 50])
        ->assertSessionHas('error', __('commerce-multi-locations::locations.stock.not_enough', ['location' => 'Gudang', 'available' => 9]));

    expect(stockAt($this->mug))->toBe(['GDG' => 9, 'TKO' => 6])->and($this->mug->fresh()->stock_quantity)->toBe(15);
    $this->get('/admin/locations/movements')->assertOk();
    $this->get("/admin/locations/stock/{$row->id}/edit")->assertOk();
});

it('reports locations that couriers cannot quote from', function () {
    $this->warehouse->update(['postal_code' => null]);

    $result = app(LocationsHealthCheck::class)->run();

    expect($result->status)->toBe('error')->and($result->message)->toContain('Gudang');
});
