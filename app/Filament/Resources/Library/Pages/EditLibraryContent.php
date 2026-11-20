<?php

namespace App\Filament\Resources\Library\Pages;

use App\Filament\Resources\Library\LibraryContentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLibraryContent extends EditRecord
{
    protected static string $resource = LibraryContentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
