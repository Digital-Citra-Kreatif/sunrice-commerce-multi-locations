<?php

declare(strict_types=1);

namespace Sunrice\CommerceMultiLocations;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Sunrice\Commerce\Contracts\Inventory;
use Sunrice\Commerce\Facades\Commerce;
use Sunrice\Commerce\Features\Feature;
use Sunrice\Commerce\Features\Features;
use Sunrice\Commerce\Inventory\DatabaseInventory;
use Sunrice\Commerce\Pricing\CalculationState;
use Sunrice\CommerceMultiLocations\Console\PlaceStockCommand;
use Sunrice\CommerceMultiLocations\Inventory\LocationInventory;
use Sunrice\CommerceMultiLocations\Models\Location;
use Sunrice\CommerceMultiLocations\Pricing\OneLocationPerDelivery;
use Sunrice\CommerceMultiLocations\Shipping\LocationPickup;

class MultiLocationsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('commerce-multi-locations')
            ->hasConfigFile()
            ->hasTranslations()
            ->hasCommands([PlaceStockCommand::class]);
    }

    public function packageRegistered(): void
    {
        $this->app->scoped(Locations::class);
        $this->app->singleton(Fulfilment::class);
        $this->app->singleton(DeletionGuards::class);
        $this->app->scoped(LocationInventory::class);
        // Stock per location when stock is counted (the inventory module) and locations are on.
        $this->app->extend(Inventory::class, fn (Inventory $inventory, $app) => $inventory instanceof DatabaseInventory && ! $inventory instanceof LocationInventory
            && $app->make(Features::class)->active('locations') ? $app->make(LocationInventory::class) : $inventory);
    }

    public function packageBooted(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Commerce::registerFeature(new Feature('locations', 'shipping', 'Locations',
            'Warehouses, shops and pickup points: stock per location, transfers, deliveries from the location that has the goods, pickup at any of them.'));
        // Deliveries leave from the location that has the goods (couriers quote and collect there).
        Commerce::shippingOriginUsing(fn (CalculationState $state) => commerce_feature('locations') ? app(Fulfilment::class)->forCart($state)?->shippingAddress() : null);
        Commerce::registerShippingMethodType(LocationPickup::class);
        Commerce::registerDiscountProvider(OneLocationPerDelivery::class);
        Commerce::registerHealthCheck(LocationsHealthCheck::class);

        Location::saved(fn () => app(Locations::class)->flush());
        Location::deleted(fn () => app(Locations::class)->flush());
    }
}
