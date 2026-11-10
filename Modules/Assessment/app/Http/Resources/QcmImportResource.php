<?php

namespace Modules\Assessment\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Assessment\Models\QcmImport;

/**
 * Suivi d’un import de questions QCM.
 *
 * @mixin QcmImport
 */
class QcmImportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'filename' => $this->filename,
            'status' => $this->status->value,
            'dry_run' => $this->dry_run,
            'total_rows' => $this->total_rows,
            'imported_count' => $this->imported_count,
            'duplicate_count' => $this->duplicate_count,
            'failed_count' => $this->failed_count,
            'errors' => $this->errors ?? [],
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ] : null),
        ];
    }
}
