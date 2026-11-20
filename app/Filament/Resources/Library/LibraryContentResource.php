<?php

namespace App\Filament\Resources\Library;

use App\Filament\Resources\Library\Pages\CreateLibraryContent;
use App\Filament\Resources\Library\Pages\EditLibraryContent;
use App\Filament\Resources\Library\Pages\ListLibraryContents;
use App\Filament\Resources\Library\Schemas\LibraryContentForm;
use App\Filament\Resources\Library\Tables\LibraryContentsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Library\Models\Content;
use UnitEnum;

class LibraryContentResource extends Resource
{
    protected static ?string $model = Content::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Bibliothèque';

    protected static ?string $navigationLabel = 'Contenus';

    protected static ?string $modelLabel = 'contenu';

    protected static ?string $pluralModelLabel = 'contenus';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return LibraryContentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LibraryContentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLibraryContents::route('/'),
            'create' => CreateLibraryContent::route('/create'),
            'edit' => EditLibraryContent::route('/{record}/edit'),
        ];
    }
}
