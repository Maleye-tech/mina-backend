<?php

namespace Modules\WellBeing\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\WellBeing\Filament\Resources\WellbeingArticleResource\Pages\CreateWellbeingArticle;
use Modules\WellBeing\Filament\Resources\WellbeingArticleResource\Pages\EditWellbeingArticle;
use Modules\WellBeing\Filament\Resources\WellbeingArticleResource\Pages\ListWellbeingArticles;
use Modules\WellBeing\Filament\Resources\WellbeingArticleResource\Pages\ViewWellbeingArticle;
use Modules\WellBeing\Filament\Resources\WellbeingArticleResource\Schemas\WellbeingArticleForm;
use Modules\WellBeing\Filament\Resources\WellbeingArticleResource\Schemas\WellbeingArticleInfolist;
use Modules\WellBeing\Filament\Resources\WellbeingArticleResource\Tables\WellbeingArticlesTable;
use Modules\WellBeing\Models\WellbeingArticle;
use UnitEnum;

/**
 * Administration des fiches de prévention Mina Care.
 */
class WellbeingArticleResource extends Resource
{
    protected static ?string $model = WellbeingArticle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Bien-être';

    protected static ?string $navigationLabel = 'Fiches de prévention';

    protected static ?string $modelLabel = 'fiche de prévention';

    protected static ?string $pluralModelLabel = 'fiches de prévention';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return WellbeingArticleForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WellbeingArticleInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WellbeingArticlesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWellbeingArticles::route('/'),
            'create' => CreateWellbeingArticle::route('/create'),
            'view' => ViewWellbeingArticle::route('/{record}'),
            'edit' => EditWellbeingArticle::route('/{record}/edit'),
        ];
    }
}
