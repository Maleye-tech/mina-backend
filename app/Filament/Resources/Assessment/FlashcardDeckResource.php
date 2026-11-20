<?php

namespace App\Filament\Resources\Assessment;

use App\Filament\Resources\Assessment\Pages\CreateFlashcardDeck;
use App\Filament\Resources\Assessment\Pages\EditFlashcardDeck;
use App\Filament\Resources\Assessment\Pages\ListFlashcardDecks;
use App\Filament\Resources\Assessment\Schemas\FlashcardDeckForm;
use App\Filament\Resources\Assessment\Tables\FlashcardDecksTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Assessment\Models\FlashcardDeck;
use UnitEnum;

class FlashcardDeckResource extends Resource
{
    protected static ?string $model = FlashcardDeck::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Évaluation';

    protected static ?string $navigationLabel = 'Flashcards';

    protected static ?string $modelLabel = 'paquet de cartes';

    protected static ?string $pluralModelLabel = 'paquets de cartes';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return FlashcardDeckForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FlashcardDecksTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFlashcardDecks::route('/'),
            'create' => CreateFlashcardDeck::route('/create'),
            'edit' => EditFlashcardDeck::route('/{record}/edit'),
        ];
    }
}
