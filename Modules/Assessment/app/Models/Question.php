<?php

namespace Modules\Assessment\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Assessment\Database\Factories\QuestionFactory;
use Modules\Assessment\Enums\ContentStatus;
use Modules\Assessment\Enums\Difficulty;
use Modules\Assessment\Enums\QuestionType;
use Modules\Library\Models\Level;
use Modules\Library\Models\Subject;

class Question extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'assessment_questions';

    protected $fillable = [
        'subject_id',
        'level_id',
        'question',
        'question_type',
        'difficulty',
        'status',
        'explanation',
        'source_reference',
        'fingerprint',
    ];

    protected $attributes = [
        'question_type' => 'single',
        'difficulty' => 'medium',
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'question_type' => QuestionType::class,
            'difficulty' => Difficulty::class,
            'status' => ContentStatus::class,
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

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('sort_order');
    }

    public function correctOptions(): HasMany
    {
        return $this->options()->where('is_correct', true);
    }

    public function attemptAnswers(): HasMany
    {
        return $this->hasMany(AttemptAnswer::class);
    }

    /**
     * Uniquement les questions publiées.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ContentStatus::Published);
    }

    /**
     * Uniquement les questions d'une difficulté donnée.
     */
    public function scopeOfDifficulty(Builder $query, Difficulty $difficulty): Builder
    {
        return $query->where('difficulty', $difficulty);
    }

    protected static function newFactory(): QuestionFactory
    {
        return QuestionFactory::new();
    }
}
