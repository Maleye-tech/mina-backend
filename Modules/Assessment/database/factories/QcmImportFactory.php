<?php

namespace Modules\Assessment\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Assessment\Enums\ImportStatus;
use Modules\Assessment\Models\QcmImport;

/**
 * @extends Factory<QcmImport>
 */
#[UseModel(QcmImport::class)]
class QcmImportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'filename' => 'import-qcm.csv',
            'file_path' => 'assessment-imports/import-qcm.csv',
            'status' => ImportStatus::Pending,
            'dry_run' => false,
            'total_rows' => 0,
            'imported_count' => 0,
            'duplicate_count' => 0,
            'failed_count' => 0,
            'errors' => null,
            'started_at' => null,
            'finished_at' => null,
        ];
    }

    /**
     * Import simulé (aucune écriture en base).
     */
    public function dryRun(): static
    {
        return $this->state(fn (array $attributes) => [
            'dry_run' => true,
        ]);
    }

    /**
     * Import terminé avec des statistiques cohérentes.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ImportStatus::Completed,
            'total_rows' => $attributes['total_rows'] ?? 100,
            'imported_count' => $attributes['imported_count'] ?? 95,
            'duplicate_count' => $attributes['duplicate_count'] ?? 3,
            'failed_count' => $attributes['failed_count'] ?? 2,
            'started_at' => now()->subMinutes(5),
            'finished_at' => now(),
        ]);
    }

    /**
     * Import échoué.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ImportStatus::Failed,
            'started_at' => now()->subMinutes(5),
            'finished_at' => now(),
        ]);
    }
}
