<?php

namespace Modules\WellBeing\Filament\Resources\WellbeingArticleResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\WellBeing\Filament\Resources\WellbeingArticleResource;

class ListWellbeingArticles extends ListRecords
{
    protected static string $resource = WellbeingArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
