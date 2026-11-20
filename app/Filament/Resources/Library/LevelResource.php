<?php

namespace App\Filament\Resources\Library;

use App\Filament\Resources\Library\Pages\CreateLevel;
use App\Filament\Resources\Library\Pages\EditLevel;
use App\Filament\Resources\Library\Pages\ListLevels;
use App\Filament\Resources\Library\Schemas\LevelForm;
use App\Filament\Resources\Library\Tables\LevelsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Library\Models\Level;
use UnitEnum;

class LevelResource extends Resource
{
    protected static ?string $model = Level::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBars3BottomLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Bibliothèque';

    protected static ?string $navigationLabel = 'Niveaux';

    protected static ?string $modelLabel = 'niveau';

    protected static ?string $pluralModelLabel = 'niveaux';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return LevelForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LevelsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLevels::route('/'),
            'create' => CreateLevel::route('/create'),
            'edit' => EditLevel::route('/{record}/edit'),
        ];
    }
}
