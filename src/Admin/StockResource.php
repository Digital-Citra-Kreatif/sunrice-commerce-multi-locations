<?php

declare(strict_types=1);

namespace Sunrice\CommerceMultiLocations\Admin;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Sunrice\Admin\Resources\Forms\Form;
use Sunrice\Admin\Resources\Forms\NumberInput;
use Sunrice\Admin\Resources\Forms\Select;
use Sunrice\Admin\Resources\Forms\Textarea;
use Sunrice\Admin\Resources\Resource;
use Sunrice\Admin\Resources\Tables\Action;
use Sunrice\Admin\Resources\Tables\ActionFailed;
use Sunrice\Admin\Resources\Tables\Column;
use Sunrice\Admin\Resources\Tables\Filter;
use Sunrice\Admin\Resources\Tables\Table;
use Sunrice\Admin\Support\Card;
use Sunrice\Commerce\Inventory\Actions\AdjustStock;
use Sunrice\CommerceMultiLocations\Actions\AdjustLocationStock;
use Sunrice\CommerceMultiLocations\Actions\TransferStock;
use Sunrice\CommerceMultiLocations\Locations;
use Sunrice\CommerceMultiLocations\Models\Location;
use Sunrice\CommerceMultiLocations\Models\LocationStock;
use Sunrice\CommerceMultiLocations\Models\Movement;

/**
 * Locations → Stock: how many of each variant every location has, with
 * Adjust (receive, count, write off) and Transfer (to another location).
 * Rows appear with a variant's first stock at a location; stock from before
 * locations goes to the default one (php artisan locations:place-stock).
 */
final class StockResource extends Resource
{
    protected static string $model = LocationStock::class;

    public static function key(): string
    {
        return 'stock';
    }

    public static function label(): string
    {
        return 'Stock';
    }

    public static function singularLabel(): string
    {
        return 'Stock';
    }

    public static function navigationGroup(): string
    {
        return 'Locations';
    }

    public static function navigationIcon(): string
    {
        return 'boxes';
    }

    public static function navigationSort(): int
    {
        return 20;
    }

    /** @return Builder<Model> */
    public static function query(): Builder
    {
        return parent::query()->with(['location', 'variant']);
    }

    /** Stock changes only through Adjust and Transfer, so the movements add up (the edit page shows the history). */
    public static function can(Authorizable $user, string $ability, ?Model $record = null): bool
    {
        return ! in_array($ability, ['create', 'delete'], true);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function title(Model $record): string
    {
        return $record instanceof LocationStock ? trim(($record->variant->sku ?? '').' · '.($record->location->name ?? ''), ' ·') : parent::title($record);
    }

    public static function aside(Model $record, Authorizable $user): array
    {
        /** @var LocationStock $record */
        $movements = $record->movements()->limit(20)->get();

        return [
            Card::make('Stock')->rows(['At '.$record->location->name => (string) $record->quantity]),
            Card::make('Movements')->list('Latest', $movements->map(fn (Movement $m) => [
                'label' => ($m->delta > 0 ? '+' : '').$m->delta.' → '.$m->quantity_after,
                'meta' => trim((MovementResource::REASONS[$m->reason] ?? $m->reason).($m->note ? ": {$m->note}" : '')), 'at' => $m->created_at,
            ]), empty: 'No changes yet.'),
        ];
    }

    public static function table(Table $table): Table
    {
        $locations = fn () => app(Locations::class)->all()->pluck('name', 'id')->all();

        return $table
            ->columns([
                Column::make('variant.sku')->label('SKU')->searchable(),
                Column::make('variant.title')->label('Variant'),
                Column::make('location.name')->label('Location'),
                Column::make('quantity')->label('Stock')->sortable(),
            ])
            ->filters([
                Filter::make('location_id')->label('Location')->options($locations),
            ])
            ->actions([
                Action::make('adjust')->label('Adjust')
                    ->form(
                        Select::make('mode')->options(['add' => 'Add or remove (+/−)', 'set' => 'Set to (a count)'])->required()->default('add'),
                        NumberInput::make('quantity')->integer()->required(),
                        Select::make('reason')->options(['manual' => 'Received or written off', 'correction' => 'Stock count', 'refund' => 'Returned by a customer', 'import' => 'Import'])->required()->default('manual'),
                        Textarea::make('note')->label('Note (optional)')->maxLength(500),
                    )
                    ->handle(function (Model $row, array $input): string {
                        /** @var LocationStock $row */
                        try {
                            app(AdjustLocationStock::class)->handle($row->location, $row->variant, (int) $input['quantity'], ($input['mode'] ?? 'add') === 'set',
                                in_array($input['reason'] ?? '', AdjustStock::REASONS, true) ? $input['reason'] : 'manual', $input['note'] ?? null, auth()->id());
                        } catch (ValidationException $e) {
                            throw new ActionFailed(collect($e->errors())->flatten()->first() ?? $e->getMessage());
                        }

                        return __('commerce-multi-locations::locations.stock.adjusted', ['item' => $row->variant->sku ?: $row->variant->title, 'location' => $row->location->name,
                            'quantity' => (int) LocationStock::query()->whereKey($row->id)->value('quantity')]);
                    }),
                Action::make('transfer')->label('Transfer')
                    ->form(
                        Select::make('to')->label('To location')->options($locations)->required(),
                        NumberInput::make('quantity')->integer()->min(1)->required(),
                        Textarea::make('note')->label('Why (optional)')->maxLength(500),
                    )
                    ->handle(function (Model $row, array $input): string {
                        /** @var LocationStock $row */
                        $to = Location::query()->findOrFail((int) $input['to']);
                        try {
                            app(TransferStock::class)->handle($row->variant, $row->location, $to, (int) $input['quantity'], auth()->id(), $input['note'] ?? null);
                        } catch (ValidationException $e) {
                            throw new ActionFailed(collect($e->errors())->flatten()->first() ?? $e->getMessage());
                        }

                        return __('commerce-multi-locations::locations.stock.transferred', ['quantity' => (int) $input['quantity'],
                            'item' => $row->variant->sku ?: $row->variant->title, 'from' => $row->location->name, 'to' => $to->name]);
                    }),
            ])
            ->defaultSort('id')
            ->perPage(50)
            ->exportable();
    }
}
