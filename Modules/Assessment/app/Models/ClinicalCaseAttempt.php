<?php

namespace Modules\Assessment\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Assessment\Database\Factories\ClinicalCaseAttemptFactory;
use Modules\Assessment\Enums\AttemptStatus;

class ClinicalCaseAttempt extends Model
{
    use HasFactory;

    protected $table = 'assessment_clinical_case_attempts';

    protected $fillable = [
        'user_id',
        'clinical_case_id',
        'status',
        'completed_at',
    ];

    protected $attributes = [
        'status' => 'in_progress',
    ];

    protected function casts(): array
    {
        return [
            'status' => AttemptStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function clinicalCase(): BelongsTo
    {
        return $this->belongsTo(ClinicalCase::class);
    }

    public function stepAnswers(): HasMany
    {
        return $this->hasMany(ClinicalCaseStepAnswer::class);
    }

    protected static function newFactory(): ClinicalCaseAttemptFactory
    {
        return ClinicalCaseAttemptFactory::new();
    }
}
