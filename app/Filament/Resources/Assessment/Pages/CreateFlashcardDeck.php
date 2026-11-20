<?php

namespace App\Filament\Resources\Assessment\Pages;

use App\Filament\Resources\Assessment\FlashcardDeckResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFlashcardDeck extends CreateRecord
{
    protected static string $resource = FlashcardDeckResource::class;
}
