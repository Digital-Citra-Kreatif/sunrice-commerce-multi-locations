<?php

declare(strict_types=1);

namespace Sunrice\CommerceMultiLocations;

use Sunrice\Admin\BasePlugin;
use Sunrice\Admin\Panel;
use Sunrice\Commerce\CommercePlugin;
use Sunrice\CommerceMultiLocations\Admin\LocationResource;
use Sunrice\CommerceMultiLocations\Admin\MovementResource;
use Sunrice\CommerceMultiLocations\Admin\StockResource;

/**
 * The locations screens, added after the store in the app's panel provider:
 *
 *     $panel->plugin(CmsPlugin::make())->plugin(CommercePlugin::make())->plugin(MultiLocationsPlugin::make());
 *
 * Locations → Locations, Stock and Movements (permissions
 * locations.locations.*, locations.stock.*, locations.movements.*).
 */
final class MultiLocationsPlugin extends BasePlugin
{
    public const ID = 'locations';

    public function id(): string
    {
        return self::ID;
    }

    /** @return list<string> */
    public function requires(): array
    {
        return [CommercePlugin::ID];
    }

    public function register(Panel $panel): void
    {
        $panel->resources(LocationResource::class, StockResource::class, MovementResource::class);
    }
}
