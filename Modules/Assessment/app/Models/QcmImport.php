<?php

namespace Modules\Assessment\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Assessment\Database\Factories\QcmImportFactory;
use Modules\Assessment\Enums\ImportStatus;

class QcmImport extends Model
{
    use HasFactory;

    protected $table = 'assessment_imports';

    protected $fillable = [
        'user_id',
        'filename',
        'file_path',
        'status',
        'dry_run',
        'total_rows',
        'imported_count',
        'duplicate_count',
        'failed_count',
        'errors',
        'started_at',
        'finished_at',
    ];

    protected $attributes = [
        'status' => 'pending',
        'dry_run' => false,
        'total_rows' => 0,
        'imported_count' => 0,
        'duplicate_count' => 0,
        'failed_count' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => ImportStatus::class,
            'dry_run' => 'boolean',
            'total_rows' => 'integer',
            'imported_count' => 'integer',
            'duplicate_count' => 'integer',
            'failed_count' => 'integer',
            'errors' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function newFactory(): QcmImportFactory
    {
        return QcmImportFactory::new();
    }
}
