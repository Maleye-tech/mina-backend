<?php

namespace App\Filament\Resources\Assessment\Pages;

use App\Filament\Resources\Assessment\QcmImportResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListQcmImports extends ListRecords
{
    protected static string $resource = QcmImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
