<?php

namespace App\Filament\Resources\ProfessionalProfileResource\Pages;

use App\Filament\Resources\ProfessionalProfileResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProfessionalProfiles extends ListRecords
{
    protected static string $resource = ProfessionalProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Nouveau professionnel'),
        ];
    }
}
