# Sunrice Commerce Multi-Locations

Multiple locations for [Sunrice Commerce](https://github.com/Digital-Citra-Kreatif/sunrice-commerce):
warehouses, shops and pickup points.

- **Stock per location.** Each tracked variant's stock is kept per location; the variant's
  `stock_quantity` stays the sum, so listings, feeds, reports and low-stock alerts work as before.
- **Deliveries from the location that has the goods.** Checkout quotes couriers (Biteship, flat
  rates…) from the first location deliveries leave from that has every item, and takes the stock
  there. When no single location has everything, checkout refuses the delivery
  (`one_location_per_delivery`).
- **Pickup at any location.** The *Local pickup* shipping method offers each location marked for
  pickup; the order takes its stock at the location the customer chose.
- **Cancellations and refunds** give stock back to the location it came from.
- **Transfers** between locations, **adjustments** per location and a **movement** history.
- **For extensions:** `Fulfilment::resolveUsing()` lets a package say which location a cart's
  lines come from (rentals do); a delivery from several of them is refused.

## Install

```bash
composer require sunrice/commerce-multi-locations
php artisan migrate
```

Add the plugin after the store in your panel provider:

```php
$panel->plugin(CmsPlugin::make())->plugin(CommercePlugin::make())->plugin(MultiLocationsPlugin::make());
```

Then add your locations in **Locations → Locations** (the first one is the default) and put the stock
you already have there:

```bash
php artisan locations:place-stock
```

Stock changed without a location (the product editor, **Shop → Inventory**, a CSV import) is made
at the default location. Change it per location in **Locations → Stock** (Adjust, Transfer).

The module can be switched off in Settings → Store → Features (*Locations*); stock is then counted for
the whole store again.

## Configuration

`config/commerce-multi-locations.php` (`php artisan vendor:publish --tag=commerce-multi-locations-config`):

| Key | Default | |
| --- | --- | --- |
| `one_location_per_delivery` | `true` | Refuse a delivery no single location can send. `false`: quote from the first location and take the stock wherever it is. |
| `default_location` | first active | The code of the location that holds stock changed without a location. |

## Permissions

`locations.locations.*`, `locations.stock.view` / `.update` (Adjust and Transfer) and
`locations.movements.view`.

## For extensions

```php
use Sunrice\CommerceMultiLocations\DeletionGuards;
use Sunrice\CommerceMultiLocations\Fulfilment;

// The locations a cart's lines come from, in order, or null to leave it to stock.
app(Fulfilment::class)->resolveUsing(fn (CalculationState $state): ?array => [...]);

// Why a location can't be deleted (your records point at it), or null.
app(DeletionGuards::class)->register(fn (Location $location): ?string => ...);
```

`LocationInventory` (bound as commerce's `Contracts\Inventory` while the module is on) adds
`adjustAt()`, `transfer()` and `byLocation()`.

## Development

```bash
composer install
composer test
composer lint
composer analyse
```

`sunrice/commerce` (and Sunrice) come from Packagist. To work against a local checkout of
`../sunrice-commerce`, copy `composer.json` to `composer.local.json`, add it as a `path` repository
there, and run `COMPOSER=composer.local.json composer install`.
