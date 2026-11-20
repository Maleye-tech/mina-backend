<?php

namespace App\Filament\Resources\Assessment\Pages;

use App\Filament\Resources\Assessment\ClinicalCaseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListClinicalCases extends ListRecords
{
    protected static string $resource = ClinicalCaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
