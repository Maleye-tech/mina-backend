<?php

namespace App\Filament\Resources\Assessment\Pages;

use App\Filament\Resources\Assessment\ClinicalCaseResource;
use Filament\Resources\Pages\EditRecord;

class EditClinicalCase extends EditRecord
{
    protected static string $resource = ClinicalCaseResource::class;
}
