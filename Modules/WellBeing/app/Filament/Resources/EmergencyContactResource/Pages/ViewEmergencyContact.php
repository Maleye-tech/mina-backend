<?php

namespace Modules\WellBeing\Filament\Resources\EmergencyContactResource\Pages;

use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Modules\WellBeing\Filament\Resources\EmergencyContactResource;

class ViewEmergencyContact extends ViewRecord
{
    protected static string $resource = EmergencyContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
