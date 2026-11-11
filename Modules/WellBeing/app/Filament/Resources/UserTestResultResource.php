<?php

namespace Modules\WellBeing\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\WellBeing\Filament\Resources\UserTestResultResource\Pages\ListUserTestResults;
use Modules\WellBeing\Filament\Resources\UserTestResultResource\Pages\ViewUserTestResult;
use Modules\WellBeing\Filament\Resources\UserTestResultResource\Schemas\UserTestResultInfolist;
use Modules\WellBeing\Filament\Resources\UserTestResultResource\Tables\UserTestResultsTable;
use Modules\WellBeing\Models\UserTestResult;
use UnitEnum;

/**
 * Consultation en lecture seule de l'historique des résultats.
 */
class UserTestResultResource extends Resource
{
    protected static ?string $model = UserTestResult::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Bien-être';

    protected static ?string $navigationLabel = 'Résultats des tests';

    protected static ?string $modelLabel = 'résultat de test';

    protected static ?string $pluralModelLabel = 'résultats des tests';

    protected static ?int $navigationSort = 40;

    public static function infolist(Schema $schema): Schema
    {
        return UserTestResultInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UserTestResultsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUserTestResults::route('/'),
            'view' => ViewUserTestResult::route('/{record}'),
        ];
    }
}
