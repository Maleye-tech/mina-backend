<?php

namespace Modules\WellBeing\Filament\Resources\WellbeingArticleResource\Pages;

use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Modules\WellBeing\Filament\Resources\WellbeingArticleResource;

class ViewWellbeingArticle extends ViewRecord
{
    protected static string $resource = WellbeingArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
