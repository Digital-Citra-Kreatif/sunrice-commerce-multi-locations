<?php

declare(strict_types=1);

namespace Sunrice\CommerceMultiLocations;

use Illuminate\Support\Collection;
use Sunrice\CommerceMultiLocations\Models\Location;

/** The store's locations, in their order (Shop → Locations), read once per request. */
class Locations
{
    /** @var Collection<int, Location>|null */
    protected ?Collection $all = null;

    /** @return Collection<int, Location> every location, active or not */
    public function all(): Collection
    {
        return $this->all ??= Location::query()->orderBy('sort_order')->orderBy('id')->get();
    }

    /** @return Collection<int, Location> */
    public function active(): Collection
    {
        return $this->all()->filter(fn (Location $l) => $l->active)->values();
    }

    /** @return Collection<int, Location> the active locations deliveries can leave from */
    public function shipping(): Collection
    {
        return $this->active()->filter(fn (Location $l) => $l->ships_from)->values();
    }

    /** @return Collection<int, Location> the active locations customers can collect orders at */
    public function pickup(): Collection
    {
        return $this->active()->filter(fn (Location $l) => $l->pickup)->values();
    }

    public function any(): bool
    {
        return $this->all()->isNotEmpty();
    }

    public function find(int $id): ?Location
    {
        return $this->all()->firstWhere('id', $id);
    }

    /**
     * Where stock changed without a location goes (the product editor, the
     * stock screen, an import): the configured code, else the first active
     * location, else the first.
     */
    public function default(): ?Location
    {
        $code = (string) config('commerce-multi-locations.default_location', '');

        return ($code !== '' ? $this->all()->firstWhere('code', $code) : null) ?? $this->active()->first() ?? $this->all()->first();
    }

    /** Forget what this request read (after locations change). */
    public function flush(): void
    {
        $this->all = null;
    }
}
