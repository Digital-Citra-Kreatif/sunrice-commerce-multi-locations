<?php

declare(strict_types=1);

namespace Sunrice\CommerceMultiLocations\Admin;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Sunrice\Admin\Resources\Forms\Form;
use Sunrice\Admin\Resources\Resource;
use Sunrice\Admin\Resources\Tables\Column;
use Sunrice\Admin\Resources\Tables\Filter;
use Sunrice\Admin\Resources\Tables\Table;
use Sunrice\CommerceMultiLocations\Locations;
use Sunrice\CommerceMultiLocations\Models\Movement;

/** Locations → Movements: every change of a location's stock, read-only. */
final class MovementResource extends Resource
{
    public const REASONS = ['order' => 'Order', 'order_edit' => 'Order edited', 'cancel' => 'Cancelled', 'refund' => 'Refunded', 'manual' => 'Manual',
        'correction' => 'Stock count', 'import' => 'Import', 'transfer' => 'Transfer', 'reconcile' => 'Placed at location'];

    protected static string $model = Movement::class;

    public static function key(): string
    {
        return 'movements';
    }

    public static function label(): string
    {
        return 'Movements';
    }

    public static function singularLabel(): string
    {
        return 'Movement';
    }

    public static function navigationGroup(): string
    {
        return 'Locations';
    }

    public static function navigationIcon(): string
    {
        return 'history';
    }

    public static function navigationSort(): int
    {
        return 30;
    }

    /** @return Builder<Model> */
    public static function query(): Builder
    {
        return parent::query()->with('location')->leftJoin('commerce_variants as v', 'v.id', '=', 'commerce_location_movements.variant_id')
            ->select('commerce_location_movements.*', 'v.sku as variant_sku');
    }

    public static function can(Authorizable $user, string $ability, ?Model $record = null): bool
    {
        return ! in_array($ability, ['create', 'update', 'delete'], true);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Column::make('created_at')->label('When')->sortable(),
                Column::make('variant_sku')->label('SKU'),
                Column::make('location.name')->label('Location'),
                Column::make('delta')->label('Change')->format(fn ($d) => ((int) $d > 0 ? '+' : '').(int) $d),
                Column::make('quantity_after')->label('After'),
                Column::make('reason')->badge()->format(fn (?string $r) => self::REASONS[$r ?? ''] ?? (string) $r),
                Column::make('reference')->label('For')->state(fn (Model $m) => $m instanceof Movement && $m->reference_id !== null ? class_basename((string) $m->reference_type).' #'.$m->reference_id : ''),
                Column::make('note'),
            ])
            ->filters([
                Filter::make('location_id')->label('Location')->options(fn () => app(Locations::class)->all()->pluck('name', 'id')->all()),
                Filter::make('reason')->options(self::REASONS),
            ])
            ->defaultSort('-created_at')
            ->perPage(50)
            ->exportable();
    }
}
