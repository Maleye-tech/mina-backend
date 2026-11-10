<?php

namespace Modules\Assessment\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Assessment\Enums\ImportStatus;
use Modules\Assessment\Models\QcmImport;
use Modules\Assessment\Services\QcmImportService;
use Throwable;

/**
 * Traiter en arrière-plan un fichier de questions QCM importé.
 */
class ProcessQcmImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Nombre de tentatives avant échec définitif.
     */
    public int $tries = 3;

    /**
     * Délais progressifs entre les tentatives, en secondes.
     *
     * @var array<int, int>
     */
    public array $backoff = [10, 60];

    public function __construct(public QcmImport $import) {}

    public function handle(QcmImportService $imports): void
    {
        $imports->process($this->import);
    }

    public function failed(?Throwable $exception): void
    {
        $this->import->update([
            'status' => ImportStatus::Failed,
            'errors' => [[
                'row' => 0,
                'errors' => [$exception?->getMessage() ?? 'L’import a échoué.'],
            ]],
            'finished_at' => now(),
        ]);
    }
}
