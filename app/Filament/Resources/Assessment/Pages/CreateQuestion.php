<?php

namespace App\Filament\Resources\Assessment\Pages;

use App\Filament\Resources\Assessment\QuestionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateQuestion extends CreateRecord
{
    protected static string $resource = QuestionResource::class;
}
