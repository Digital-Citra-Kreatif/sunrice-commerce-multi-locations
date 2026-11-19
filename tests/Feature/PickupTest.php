<?php

declare(strict_types=1);

use Sunrice\Commerce\Cart\CartView;
use Sunrice\Commerce\Checkout\CheckoutData;
use Sunrice\Commerce\Shipping\ShippingRequest;
use Sunrice\CommerceMultiLocations\Actions\TransferStock;

beforeEach(function () {
    $this->mug = mugStore(10);
    [$this->warehouse, $this->shop] = twoLocations();
});

it('offers pickup at the locations marked for it', function () {
    $cart = cartWith($this->mug, 1);
    $state = CartView::for($cart, CheckoutData::fromCart($cart)->merge(checkoutInput())->context())->state;

    $pickup = collect($state->shipping['rates'] ?? [])->where('method', 'pickup')->values();

    expect($pickup)->toHaveCount(1)
        ->and($pickup[0]['label'])->toContain('Toko Dago')
        ->and($pickup[0]['meta']['location'])->toMatchArray(['id' => $this->shop->id, 'code' => 'TKO', 'instructions' => 'Buka 09.00–21.00']);
});

it('takes a pickup order at the pickup location', function () {
    app(TransferStock::class)->handle($this->mug, $this->warehouse, $this->shop, 4);

    $order = placeOrder(cartWith($this->mug, 2), pickupInput($this->shop));

    expect($order->meta('pickup.code'))->toBe('TKO')
        ->and(stockAt($this->mug))->toBe(['GDG' => 6, 'TKO' => 2]);
});

it('refuses pickup where the items are not, even when another location has them', function () {
    app(TransferStock::class)->handle($this->mug, $this->warehouse, $this->shop, 1);

    submitCheckout(cartWith($this->mug, 2), pickupInput($this->shop))
        ->assertSessionHas('commerce.error', __('commerce-multi-locations::locations.errors.not_at_location', ['available' => 1, 'item' => 'Mug', 'location' => 'Toko Dago']));

    expect(stockAt($this->mug))->toBe(['GDG' => 9, 'TKO' => 1]);
});

it('quotes deliveries from the location as the origin', function () {
    $cart = cartWith($this->mug, 1);
    $state = CartView::for($cart, CheckoutData::fromCart($cart)->merge(checkoutInput())->context())->state;

    $origin = ShippingRequest::fromState($state)->origin;

    expect($origin->get('postal_code'))->toBe('40292')
        ->and($origin->get('phone'))->toBe('0221000001')
        ->and($origin->get('name'))->toBe('Toko Mug Gudang');
});
