<?php

namespace App\Filament\Resources\Assessment\Pages;

use App\Filament\Resources\Assessment\QcmImportResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Arr;
use Modules\Assessment\Enums\ImportStatus;
use Modules\Assessment\Jobs\ProcessQcmImport;

class CreateQcmImport extends CreateRecord
{
    protected static string $resource = QcmImportResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $file = $data['file'] ?? null;
        $path = is_array($file) ? (string) Arr::first($file) : (string) $file;

        $original = $data['original_filename'] ?? null;
        $original = is_array($original) ? (string) Arr::first($original) : (string) $original;

        unset($data['file'], $data['original_filename']);

        return [
            'user_id' => auth()->id(),
            'filename' => filled($original) ? $original : basename($path),
            'file_path' => $path,
            'status' => ImportStatus::Pending,
            'dry_run' => (bool) ($data['dry_run'] ?? false),
            'total_rows' => 0,
            'imported_count' => 0,
            'duplicate_count' => 0,
            'failed_count' => 0,
        ];
    }

    protected function afterCreate(): void
    {
        ProcessQcmImport::dispatch($this->record);

        Notification::make()
            ->success()
            ->title('Import de QCM lancé')
            ->body('Le fichier sera traité en arrière-plan. Pensez à lancer la file (php artisan queue:work).')
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
