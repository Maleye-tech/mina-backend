<?php

namespace App\Filament\Resources\Assessment\Pages;

use App\Filament\Resources\Assessment\FlashcardDeckResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFlashcardDecks extends ListRecords
{
    protected static string $resource = FlashcardDeckResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
