<?php

declare(strict_types=1);

namespace Sunrice\CommerceMultiLocations;

use Closure;
use Sunrice\CommerceMultiLocations\Models\Location;

/**
 * What keeps a location from being deleted besides its stock: extensions
 * whose records point at it (rental units) say why, and Locations → Locations
 * shows it instead of deleting.
 */
class DeletionGuards
{
    /** @var list<Closure(Location): (string|null)> */
    protected array $guards = [];

    /** @param  Closure(Location): (string|null)  $guard  the reason it can't be deleted, or null */
    public function register(Closure $guard): void
    {
        $this->guards[] = $guard;
    }

    public function refusal(Location $location): ?string
    {
        if ($location->stock()->where('quantity', '!=', 0)->exists()) {
            return __('commerce-multi-locations::locations.location.has_stock');
        }
        foreach ($this->guards as $guard) {
            if (($reason = $guard($location)) !== null) {
                return $reason;
            }
        }

        return null;
    }
}
