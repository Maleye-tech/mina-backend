<?php

namespace App\Filament\Resources\Assessment\Pages;

use App\Filament\Resources\Assessment\FlashcardDeckResource;
use Filament\Resources\Pages\EditRecord;

class EditFlashcardDeck extends EditRecord
{
    protected static string $resource = FlashcardDeckResource::class;
}
