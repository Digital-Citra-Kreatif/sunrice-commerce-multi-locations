<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Commerce\Cart\CartView;
use Sunrice\Commerce\Cart\Models\Cart;
use Sunrice\Commerce\Catalog\Actions\ScaffoldStore;
use Sunrice\Commerce\Catalog\Models\Variant;
use Sunrice\Commerce\Checkout\CheckoutData;
use Sunrice\Commerce\Commerce;
use Sunrice\Commerce\Orders\Models\Order;
use Sunrice\Commerce\Regions\RegionImporter;
use Sunrice\Commerce\Settings\StoreSettings;
use Sunrice\Commerce\Shipping\Models\ShippingMethod;
use Sunrice\Commerce\Shipping\Models\ShippingZone;
use Sunrice\CommerceMultiLocations\Models\Location;
use Sunrice\CommerceMultiLocations\Models\LocationStock;
use Sunrice\CommerceMultiLocations\Tests\TestCase;
use Workbench\App\Commerce\TestTransfer;
use Workbench\App\Models\User;

uses(TestCase::class)->in('Feature');

/**
 * A store selling a "Mug" (MUG, 50.000, 500 g, stock counted, $stock in
 * stock), delivered by a flat-rate courier or picked up, paid by test
 * transfer. No locations yet. Returns the mug.
 */
function mugStore(int $stock = 10): Variant
{
    (new RegionImporter(__DIR__.'/Fixtures/regions'))->import(4);
    app(ScaffoldStore::class)->handle();
    app(StoreSettings::class)->saveSection('general', ['name' => 'Toko Mug']);
    publishProduct('Mug', [['id' => 'mug', 'sku' => 'MUG', 'price' => 50000, 'weight' => 500, 'track_stock' => true, 'initial_stock' => $stock]]);

    $zone = ShippingZone::query()->create(['name' => 'Indonesia']);
    $zone->regions()->create(['country' => 'ID']);
    $zone->methods()->create(['type' => 'flat', 'title' => ['id' => 'Kurir toko', 'en' => 'Store courier'], 'settings' => ['cost' => 20000], 'active' => true]);
    $zone->methods()->create(['type' => 'pickup', 'title' => ['id' => 'Ambil di toko', 'en' => 'Pickup'], 'settings' => ['cost' => 0, 'locations' => []], 'active' => true]);
    app(Commerce::class)->registerPaymentMethod(TestTransfer::class);
    app(StoreSettings::class)->saveSection('payments', ['methods' => ['test_transfer' => ['active' => true, 'title' => ['id' => 'Transfer', 'en' => 'Transfer'], 'deadline_hours' => 2]]]);
    app()->forgetScopedInstances();

    return Variant::query()->where('sku', 'MUG')->firstOrFail();
}

/** A warehouse (GDG, first) and a shop (TKO, also a pickup point), with postal codes. */
function twoLocations(): array
{
    $warehouse = Location::query()->create(['code' => 'GDG', 'name' => 'Gudang', 'address' => 'Jl. Gudang 1', 'postal_code' => '40292', 'phone' => '0221000001', 'sort_order' => 0]);
    $shop = Location::query()->create(['code' => 'TKO', 'name' => 'Toko Dago', 'address' => 'Jl. Dago 9', 'postal_code' => '40135', 'phone' => '0221000002', 'pickup' => true, 'sort_order' => 1,
        'instructions' => 'Buka 09.00–21.00']);
    app()->forgetScopedInstances();

    return [$warehouse, $shop];
}

/** @return array<string, int> location code => quantity of the variant */
function stockAt(Variant $variant): array
{
    return LocationStock::query()->where('variant_id', $variant->id)->with('location')->get()
        ->mapWithKeys(fn (LocationStock $s) => [$s->location->code => $s->quantity])->sortKeys()->all();
}

/** Publishes a product entry with these variants, through Sunrice's own actions. */
function publishProduct(string $title, array $variants): void
{
    $collection = Collection::query()->where('handle', 'products')->first();
    if ($collection === null) {
        $definition = collect((require __DIR__.'/../vendor/sunrice/commerce/resources/structure/commerce.php')['blueprints'])->firstWhere('handle', 'product');
        Blueprint::query()->firstOrCreate(['handle' => 'product'], ['title' => 'Product', 'fields' => $definition['fields']]);
        $collection = Collection::query()->create(['handle' => 'products', 'title' => 'Products', 'blueprint' => 'product', 'route' => '/products/{slug}']);
    }
    $translation = app(CreateEntry::class)->handle($collection, ['title' => $title, 'slug' => Str::slug($title), 'data' => ['product' => ['variants' => $variants]]]);
    app(PublishEntry::class)->handle($translation->fresh());
}

/** A guest cart with the variant, sent as the visitor's cookie. */
function cartWith(Variant $variant, int $quantity): Cart
{
    $cart = Cart::query()->create(['token' => Str::random(64), 'status' => 'active', 'currency' => 'IDR', 'last_activity_at' => now()]);
    $cart->items()->create(['variant_id' => $variant->id, 'quantity' => $quantity]);
    test()->withCookie((string) config('commerce.cart.cookie', 'commerce_cart'), $cart->token);

    return $cart;
}

/** @return array<string, mixed> delivered by the store courier */
function checkoutInput(array $overrides = []): array
{
    $method = ShippingMethod::query()->where('type', 'flat')->value('id');

    return array_replace_recursive([
        'name' => 'Rina', 'email' => 'rina@example.com', 'phone' => '0812 3456 7890',
        'shipping_address' => ['name' => 'Rina', 'phone' => '0812 3456 7890', 'country' => 'ID', 'province_code' => '32', 'city_code' => '32.73',
            'district_code' => '32.73.01', 'line1' => 'Jl. Sarijadi 1'],
        'billing_same' => '1',
        'shipping_rate' => "flat:{$method}:default",
        'payment_method' => 'test_transfer',
    ], $overrides);
}

/** @return array<string, mixed> picked up at the location */
function pickupInput(Location $location): array
{
    $method = ShippingMethod::query()->where('type', 'pickup')->value('id');

    return ['shipping_rate' => "pickup:{$method}:location-{$location->id}"];
}

/** POSTs /checkout; returns the response (errors are in the session). */
function submitCheckout(Cart $cart, array $input = []): TestResponse
{
    app()->forgetScopedInstances();
    $input = checkoutInput($input);
    $hash = CartView::for($cart->fresh(), CheckoutData::fromCart($cart->fresh())->merge($input)->context())->state->hash();

    return test()->post('/checkout', [...$input, 'action' => 'place', 'totals_hash' => $hash]);
}

function placeOrder(Cart $cart, array $input = []): Order
{
    submitCheckout($cart, $input)->assertSessionHasNoErrors();

    return Order::query()->latest('id')->firstOrFail();
}

function actingAsSuperAdmin(): User
{
    $user = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => bcrypt('password')]);
    $user->assignRole(Role::findOrCreate((string) config('sunrice-admin.super_admin_role', 'super-admin'), 'web'));
    test()->actingAs($user, 'web');

    return $user;
}
