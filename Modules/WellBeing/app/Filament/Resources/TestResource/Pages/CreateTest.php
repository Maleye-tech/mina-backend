<?php

namespace Modules\WellBeing\Filament\Resources\TestResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\WellBeing\Filament\Resources\TestResource;

class CreateTest extends CreateRecord
{
    protected static string $resource = TestResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
