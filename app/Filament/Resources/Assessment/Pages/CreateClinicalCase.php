<?php

namespace App\Filament\Resources\Assessment\Pages;

use App\Filament\Resources\Assessment\ClinicalCaseResource;
use Filament\Resources\Pages\CreateRecord;

class CreateClinicalCase extends CreateRecord
{
    protected static string $resource = ClinicalCaseResource::class;
}
