<?php

namespace Modules\WellBeing\Filament\Resources\WellbeingArticleResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Modules\WellBeing\Filament\Resources\WellbeingArticleResource;

class EditWellbeingArticle extends EditRecord
{
    protected static string $resource = WellbeingArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
