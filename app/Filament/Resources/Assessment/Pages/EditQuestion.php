<?php

namespace App\Filament\Resources\Assessment\Pages;

use App\Filament\Resources\Assessment\QuestionResource;
use Filament\Resources\Pages\EditRecord;

class EditQuestion extends EditRecord
{
    protected static string $resource = QuestionResource::class;
}
