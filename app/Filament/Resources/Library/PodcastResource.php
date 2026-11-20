<?php

namespace App\Filament\Resources\Library;

use App\Filament\Resources\Library\Pages\CreatePodcast;
use App\Filament\Resources\Library\Pages\EditPodcast;
use App\Filament\Resources\Library\Pages\ListPodcasts;
use App\Filament\Resources\Library\Schemas\PodcastForm;
use App\Filament\Resources\Library\Tables\PodcastsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Library\Models\Podcast;
use UnitEnum;

class PodcastResource extends Resource
{
    protected static ?string $model = Podcast::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMicrophone;

    protected static string|UnitEnum|null $navigationGroup = 'Bibliothèque';

    protected static ?string $navigationLabel = 'Podcasts';

    protected static ?string $modelLabel = 'podcast';

    protected static ?string $pluralModelLabel = 'podcasts';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return PodcastForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PodcastsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPodcasts::route('/'),
            'create' => CreatePodcast::route('/create'),
            'edit' => EditPodcast::route('/{record}/edit'),
        ];
    }
}
