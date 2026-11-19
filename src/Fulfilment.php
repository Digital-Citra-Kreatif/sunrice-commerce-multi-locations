<?php

declare(strict_types=1);

namespace Sunrice\CommerceMultiLocations;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Sunrice\Commerce\Catalog\Bundles;
use Sunrice\Commerce\Catalog\Models\Variant;
use Sunrice\Commerce\Orders\Models\Order;
use Sunrice\Commerce\Pricing\CalculationState;
use Sunrice\CommerceMultiLocations\Models\Location;
use Sunrice\CommerceMultiLocations\Models\LocationStock;

/**
 * Which location an order is fulfilled from.
 *
 * For a cart: the location an extension names for its lines (a rental's
 * location, see resolveUsing()), else the first location deliveries leave
 * from that has every item in stock. For a placed order: the location the
 * customer picks up at, else the one its delivery was quoted from.
 */
class Fulfilment
{
    /** @var list<Closure(CalculationState): (list<int>|null)> */
    protected array $resolvers = [];

    public function __construct(protected Locations $locations) {}

    /**
     * Let an extension say which locations a cart's lines come from (ids,
     * in the order they appear), or null to leave it to stock. The first
     * resolver that answers wins.
     *
     * @param  Closure(CalculationState): (list<int>|null)  $resolver
     */
    public function resolveUsing(Closure $resolver): void
    {
        $this->resolvers[] = $resolver;
    }

    /** @return list<int>|null the locations an extension named for this cart */
    public function named(CalculationState $state): ?array
    {
        foreach ($this->resolvers as $resolver) {
            $ids = $resolver($state);
            if (is_array($ids) && $ids !== []) {
                return array_values(array_unique(array_map('intval', $ids)));
            }
        }

        return null;
    }

    /** Where a delivery of this cart leaves from, or null for the store address. */
    public function forCart(CalculationState $state): ?Location
    {
        $named = $this->named($state);
        if ($named !== null) {
            return $this->locations->find($named[0]);
        }
        $candidates = $this->locations->shipping();
        if ($candidates->isEmpty()) {
            return null;
        }
        $needs = $this->needs(array_map(fn ($line) => [$line->variant, $line->quantity], array_values($state->lines)));

        return $this->best($candidates, $needs);
    }

    /**
     * The location a placed order (or quote) takes its stock from: where the
     * customer picks it up, else where its delivery was quoted from; null
     * when it says neither (stock then comes from the locations in order).
     */
    public function forReference(?Model $reference): ?Location
    {
        if (! $reference instanceof Order) {
            return null;
        }
        $pickup = $reference->meta('pickup');
        if (is_array($pickup) && isset($pickup['id'], $pickup['code'])) {
            $location = $this->locations->find((int) $pickup['id']);

            return $location?->code === $pickup['code'] ? $location : null;
        }
        $origin = $reference->meta('shipping_origin');
        if (is_array($origin) && $origin !== []) {
            // The origin the order was quoted from, by its address (the name carries the store's, which can change).
            $where = fn (array $address): array => array_diff_key($address, ['name' => true]);

            return $this->locations->all()->first(fn (Location $l) => $where($l->shippingAddress()->toArray()) == $where($origin));
        }

        return null;
    }

    /** Whether the order is collected by the customer (not delivered). */
    public static function isPickup(?Model $reference): bool
    {
        return $reference instanceof Order && is_array($reference->meta('pickup'));
    }

    /**
     * Tracked variants and the quantities lines need of each, bundles
     * replaced by their components.
     *
     * @param  array<int, array{0: Variant, 1: int}>  $lines
     * @return array<int, int> variant id => quantity
     */
    public function needs(array $lines): array
    {
        $needs = [];
        foreach ($lines as [$variant, $qty]) {
            $parts = $variant->product->isBundle()
                ? array_map(fn (array $c) => [$c['variant'], $c['quantity']], app(Bundles::class)->explode($variant->product, $qty))
                : [[$variant, $qty]];
            foreach ($parts as [$part, $partQty]) {
                if ($part->track_stock && $part->backorders === 'no') {
                    $needs[$part->id] = ($needs[$part->id] ?? 0) + $partQty;
                }
            }
        }

        return $needs;
    }

    /**
     * The first location with everything; failing that, the one with the
     * most of it (checkout then refuses a delivery from one location).
     *
     * @param  Collection<int, Location>  $candidates
     * @param  array<int, int>  $needs
     */
    protected function best(Collection $candidates, array $needs): ?Location
    {
        if ($needs === []) {
            return $candidates->first();
        }
        $stock = LocationStock::query()->whereIn('variant_id', array_keys($needs))->whereIn('location_id', $candidates->pluck('id'))
            ->get()->groupBy('location_id');
        $best = null;
        $bestCovered = -1;
        foreach ($candidates as $location) {
            $have = $stock->get($location->id, collect())->pluck('quantity', 'variant_id');
            $covered = 0;
            foreach ($needs as $variantId => $qty) {
                $covered += min($qty, max(0, (int) $have->get($variantId, 0)));
            }
            if ($covered === array_sum($needs)) {
                return $location;
            }
            if ($covered > $bestCovered) {
                [$best, $bestCovered] = [$location, $covered];
            }
        }

        return $best;
    }
}
