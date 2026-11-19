<?php

declare(strict_types=1);

namespace Sunrice\CommerceMultiLocations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Sunrice\Commerce\Settings\StoreSettings;
use Sunrice\Commerce\Support\Address;

/**
 * A warehouse, shop or pickup point: where stock is kept, where deliveries
 * leave from (ships_from) and where customers collect orders (pickup).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $phone
 * @property string|null $whatsapp
 * @property string|null $address
 * @property string|null $province_code
 * @property string|null $city_code
 * @property string|null $district_code
 * @property string|null $village_code
 * @property string|null $postal_code
 * @property string|null $latitude
 * @property string|null $longitude
 * @property string|null $instructions
 * @property bool $ships_from
 * @property bool $pickup
 * @property bool $active
 * @property int $sort_order
 */
class Location extends Model
{
    protected $table = 'commerce_locations';

    protected $guarded = ['id'];

    protected $casts = ['ships_from' => 'boolean', 'pickup' => 'boolean', 'active' => 'boolean', 'sort_order' => 'integer'];

    /**
     * The location as a shipping origin (commerce's Address): where couriers
     * collect its deliveries, named "<store name> <location>".
     */
    public function shippingAddress(): Address
    {
        return Address::fromArray([
            'name' => trim(app(StoreSettings::class)->get('general.name', '').' '.$this->name),
            'phone' => $this->phone ?: $this->whatsapp,
            'line1' => $this->address,
            'province_code' => $this->province_code, 'city_code' => $this->city_code,
            'district_code' => $this->district_code, 'village_code' => $this->village_code,
            'postal_code' => $this->postal_code,
            'country' => 'ID',
            'latitude' => $this->latitude !== null ? (string) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (string) $this->longitude : null,
        ]);
    }

    /**
     * What a pickup order keeps (Order meta `pickup`): the location's id with
     * what the customer is shown.
     *
     * @return array{id: int, code: string, name: string, address: string|null, instructions: string|null}
     */
    public function pickupDetails(): array
    {
        return ['id' => $this->id, 'code' => $this->code, 'name' => $this->name, 'address' => $this->address, 'instructions' => $this->instructions];
    }

    /** @return HasMany<LocationStock, $this> */
    public function stock(): HasMany
    {
        return $this->hasMany(LocationStock::class);
    }
}
