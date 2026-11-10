<?php

namespace Modules\Assessment\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Assessment\Database\Factories\ClinicalCaseFactory;
use Modules\Assessment\Enums\ContentStatus;
use Modules\Assessment\Enums\Difficulty;
use Modules\Library\Models\Level;
use Modules\Library\Models\Subject;

class ClinicalCase extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'assessment_clinical_cases';

    protected $fillable = [
        'title',
        'presentation',
        'subject_id',
        'level_id',
        'difficulty',
        'status',
        'duration_minutes',
        'learning_goals',
    ];

    protected $attributes = [
        'difficulty' => 'medium',
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'difficulty' => Difficulty::class,
            'status' => ContentStatus::class,
            'duration_minutes' => 'integer',
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ClinicalCaseStep::class)->orderBy('sort_order');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ClinicalCaseAttempt::class);
    }

    /**
     * Uniquement les cas cliniques publiés.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ContentStatus::Published);
    }

    protected static function newFactory(): ClinicalCaseFactory
    {
        return ClinicalCaseFactory::new();
    }
}
