<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Sunrice\Commerce\Contracts\Inventory;
use Sunrice\Commerce\Inventory\Actions\AdjustStock;
use Sunrice\Commerce\Inventory\DatabaseInventory;
use Sunrice\Commerce\Orders\Listeners\ReleaseReservations;
use Sunrice\Commerce\Settings\StoreSettings;
use Sunrice\CommerceMultiLocations\Actions\TransferStock;
use Sunrice\CommerceMultiLocations\Inventory\LocationInventory;
use Sunrice\CommerceMultiLocations\Models\Movement;
use Sunrice\CommerceMultiLocations\Models\Transfer;

beforeEach(function () {
    $this->mug = mugStore(10);
});

it('counts stock for the whole store until there are locations', function () {
    expect(app(Inventory::class))->toBeInstanceOf(LocationInventory::class);

    app(AdjustStock::class)->handle($this->mug, 5);

    expect($this->mug->fresh()->stock_quantity)->toBe(15)->and(stockAt($this->mug))->toBe([]);
});

it('uses core stock counting when locations are switched off', function () {
    app(StoreSettings::class)->saveSection('features', ['locations' => false]);
    app()->forgetScopedInstances();

    expect(app(Inventory::class))->toBeInstanceOf(DatabaseInventory::class)->not->toBeInstanceOf(LocationInventory::class);
});

it('puts stock from before the locations at the default location with its next change', function () {
    twoLocations();

    app(AdjustStock::class)->handle($this->mug, 5, reason: 'manual', note: 'Delivery from supplier');

    expect(stockAt($this->mug))->toBe(['GDG' => 15])
        ->and($this->mug->fresh()->stock_quantity)->toBe(15)
        ->and(Movement::query()->orderBy('id')->pluck('reason')->all())->toBe(['reconcile', 'manual']);
});

it('places all stock at once with the command', function () {
    twoLocations();

    $this->artisan('locations:place-stock')->assertSuccessful();

    expect(stockAt($this->mug))->toBe(['GDG' => 10]);
});

it('moves stock between locations without changing the total', function () {
    [$warehouse, $shop] = twoLocations();

    $transfer = app(TransferStock::class)->handle($this->mug, $warehouse, $shop, 4, note: 'For the weekend');

    expect(stockAt($this->mug))->toBe(['GDG' => 6, 'TKO' => 4])
        ->and($this->mug->fresh()->stock_quantity)->toBe(10)
        ->and($transfer)->toBeInstanceOf(Transfer::class)
        ->and(Movement::query()->where('transfer_id', $transfer->id)->pluck('delta')->all())->toBe([-4, 4]);

    expect(fn () => app(TransferStock::class)->handle($this->mug->fresh(), $shop, $warehouse, 5))
        ->toThrow(ValidationException::class, 'Toko Dago has only 4.');
});

it('takes a delivered order from the location it ships from, and gives it back there when cancelled', function () {
    [$warehouse, $shop] = twoLocations();
    app(TransferStock::class)->handle($this->mug, $warehouse, $shop, 10);

    $order = placeOrder(cartWith($this->mug, 3));

    expect($order->shippingOrigin()->get('line1'))->toBe('Jl. Dago 9')
        ->and($order->shippingOrigin()->get('name'))->toBe('Toko Mug Toko Dago')
        ->and(stockAt($this->mug))->toBe(['GDG' => 0, 'TKO' => 7])
        ->and($this->mug->fresh()->stock_quantity)->toBe(7);

    app(ReleaseReservations::class)->release($order->fresh());

    expect(stockAt($this->mug))->toBe(['GDG' => 0, 'TKO' => 10])->and($this->mug->fresh()->stock_quantity)->toBe(10);
});

it('ships from the first location that has everything', function () {
    [$warehouse, $shop] = twoLocations();
    app(TransferStock::class)->handle($this->mug, $warehouse, $shop, 3);

    $order = placeOrder(cartWith($this->mug, 5));

    expect($order->shippingOrigin()->get('postal_code'))->toBe('40292')
        ->and(stockAt($this->mug))->toBe(['GDG' => 2, 'TKO' => 3]);
});

it('refuses a delivery no single location can send', function () {
    [$warehouse, $shop] = twoLocations();
    app(TransferStock::class)->handle($this->mug, $warehouse, $shop, 5);

    submitCheckout(cartWith($this->mug, 7))->assertSessionHas('commerce.error', __('commerce-multi-locations::locations.errors.not_together', ['available' => 5, 'item' => 'Mug']));

    expect(stockAt($this->mug))->toBe(['GDG' => 5, 'TKO' => 5])->and($this->mug->fresh()->stock_quantity)->toBe(10);
});

it('sends from several locations when one per delivery is switched off', function () {
    config(['commerce-multi-locations.one_location_per_delivery' => false]);
    [$warehouse, $shop] = twoLocations();
    app(TransferStock::class)->handle($this->mug, $warehouse, $shop, 5);

    placeOrder(cartWith($this->mug, 7));

    expect(stockAt($this->mug))->toBe(['GDG' => 0, 'TKO' => 3])->and($this->mug->fresh()->stock_quantity)->toBe(3);
});

it('lowers the default location first, then the others, when stock is set lower', function () {
    [$warehouse, $shop] = twoLocations();
    app(TransferStock::class)->handle($this->mug, $warehouse, $shop, 6);

    app(AdjustStock::class)->handle($this->mug->fresh(), 3, set: true, reason: 'correction');

    expect(stockAt($this->mug))->toBe(['GDG' => 0, 'TKO' => 3])->and($this->mug->fresh()->stock_quantity)->toBe(3);
});
