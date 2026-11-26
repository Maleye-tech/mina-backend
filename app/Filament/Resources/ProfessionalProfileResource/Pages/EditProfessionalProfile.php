<?php

namespace App\Filament\Resources\ProfessionalProfileResource\Pages;

use App\Filament\Resources\ProfessionalProfileResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;
use Modules\Appointments\Services\ProfessionalProfileService;

class EditProfessionalProfile extends EditRecord
{
    protected static string $resource = ProfessionalProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function afterSave(): void
    {
        DB::transaction(function (): void {
            app(ProfessionalProfileService::class)->syncLegacyProfile($this->getRecord());
        });
    }
}
