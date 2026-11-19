<?php

declare(strict_types=1);

namespace Sunrice\CommerceMultiLocations\Admin;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Sunrice\Admin\Resources\Forms\Form;
use Sunrice\Admin\Resources\Forms\NumberInput;
use Sunrice\Admin\Resources\Forms\Section;
use Sunrice\Admin\Resources\Forms\Textarea;
use Sunrice\Admin\Resources\Forms\TextInput;
use Sunrice\Admin\Resources\Forms\Toggle;
use Sunrice\Admin\Resources\Resource;
use Sunrice\Admin\Resources\Tables\ActionFailed;
use Sunrice\Admin\Resources\Tables\Column;
use Sunrice\Admin\Resources\Tables\Table;
use Sunrice\CommerceMultiLocations\Locations;
use Sunrice\CommerceMultiLocations\Models\Location;

/** Locations → Locations: warehouses, shops and pickup points (permissions locations.locations.*). */
final class LocationResource extends Resource
{
    protected static string $model = Location::class;

    public static function key(): string
    {
        return 'locations';
    }

    public static function label(): string
    {
        return 'Locations';
    }

    public static function singularLabel(): string
    {
        return 'Location';
    }

    public static function navigationGroup(): string
    {
        return 'Locations';
    }

    public static function navigationIcon(): string
    {
        return 'warehouse';
    }

    public static function navigationSort(): int
    {
        return 10;
    }

    /** @return Builder<Model> */
    public static function query(): Builder
    {
        return parent::query()->withSum('stock as stock_total', 'quantity');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Location')->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(120),
                TextInput::make('code')->required()->maxLength(20)->unique()->rules(['regex:/^[A-Z0-9-]+$/'])
                    ->help('Short and in capitals (JKT, GDG-1).'),
                Toggle::make('ships_from')->label('Ships deliveries')->default(true)->help('Couriers collect deliveries here.'),
                Toggle::make('pickup')->label('Customers pick up here')->default(false)->help('Offered by the Local pickup shipping method.'),
                Toggle::make('active')->default(true)->help('Inactive locations are not offered for pickup or used for deliveries; their stock still counts.'),
                NumberInput::make('sort_order')->label('Order')->integer()->min(0)->default(0)->help('Stock is taken from the first location that has it.'),
            ]),
            Section::make('Address')->columns(2)->schema([
                Textarea::make('address')->rows(3)->maxLength(500)->span(2)->help('Where couriers collect and customers pick up.'),
                TextInput::make('postal_code')->label('Postal code')->maxLength(5)->rules(['nullable', 'digits:5'])->help('Needed for courier rates from this location.'),
                TextInput::make('phone')->label('Phone for couriers')->maxLength(30)->help('Empty: the WhatsApp number.'),
                TextInput::make('whatsapp')->label('WhatsApp number')->maxLength(20)->rules(['nullable', 'regex:/^\+?[0-9 ()-]{8,20}$/']),
                TextInput::make('latitude')->maxLength(20)->rules(['nullable', 'numeric', 'between:-90,90'])->help('From Google Maps (right-click the place). Needed for GoSend and Grab.'),
                TextInput::make('longitude')->maxLength(20)->rules(['nullable', 'numeric', 'between:-180,180']),
                Textarea::make('instructions')->rows(3)->maxLength(1000)->span(2)->help('For customers picking up: opening hours, what to bring.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Column::make('name')->searchable()->sortable(),
                Column::make('code')->searchable()->sortable(),
                Column::make('stock_total')->label('Stock')->format(fn ($v) => (string) (int) $v),
                Column::make('ships_from')->label('Ships')->boolean(),
                Column::make('pickup')->label('Pickup')->boolean(),
                Column::make('active')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->exportable();
    }

    public static function deleteRecord(Model $record): void
    {
        if ($record instanceof Location && $record->stock()->where('quantity', '!=', 0)->exists()) {
            throw new ActionFailed(__('commerce-multi-locations::locations.location.has_stock'));
        }
        parent::deleteRecord($record);
        app(Locations::class)->flush();
    }
}
