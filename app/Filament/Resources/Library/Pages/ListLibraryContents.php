<?php

namespace App\Filament\Resources\Library\Pages;

use App\Filament\Resources\Library\LibraryContentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLibraryContents extends ListRecords
{
    protected static string $resource = LibraryContentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
