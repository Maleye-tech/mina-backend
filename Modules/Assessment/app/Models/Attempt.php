<?php

namespace Modules\Assessment\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Assessment\Database\Factories\AttemptFactory;
use Modules\Assessment\Enums\AttemptStatus;
use Modules\Library\Models\Level;
use Modules\Library\Models\Subject;

class Attempt extends Model
{
    use HasFactory;

    protected $table = 'assessment_attempts';

    protected $fillable = [
        'user_id',
        'subject_id',
        'level_id',
        'status',
        'score',
        'correct_count',
        'total_questions',
        'time_spent_seconds',
        'completed_at',
    ];

    protected $attributes = [
        'status' => 'in_progress',
        'correct_count' => 0,
        'total_questions' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => AttemptStatus::class,
            'score' => 'decimal:2',
            'correct_count' => 'integer',
            'total_questions' => 'integer',
            'time_spent_seconds' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AttemptAnswer::class);
    }

    /**
     * Uniquement les tentatives d'un utilisateur donné.
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    /**
     * Uniquement les tentatives terminées.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', AttemptStatus::Completed);
    }

    protected static function newFactory(): AttemptFactory
    {
        return AttemptFactory::new();
    }
}
