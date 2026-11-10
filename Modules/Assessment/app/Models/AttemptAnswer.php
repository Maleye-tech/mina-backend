<?php

namespace Modules\Assessment\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Assessment\Database\Factories\AttemptAnswerFactory;

class AttemptAnswer extends Model
{
    use HasFactory;

    protected $table = 'assessment_attempt_answers';

    protected $fillable = [
        'attempt_id',
        'question_id',
        'selected_option_ids',
        'is_correct',
        'answered_at',
    ];

    protected $attributes = [
        'is_correct' => false,
    ];

    protected function casts(): array
    {
        return [
            'selected_option_ids' => 'array',
            'is_correct' => 'boolean',
            'answered_at' => 'datetime',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(Attempt::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /**
     * Identifiants des bonnes options de la question associée.
     *
     * La question doit être chargée avec ses options (eager loading) pour
     * éviter les requêtes N+1 lors de la correction.
     *
     * @return array<int, int>
     */
    public function correctOptionIds(): array
    {
        $options = $this->question?->options;

        if ($options === null) {
            return [];
        }

        return $options
            ->filter(fn ($option): bool => $option->is_correct)
            ->pluck('id')
            ->values()
            ->all();
    }

    protected static function newFactory(): AttemptAnswerFactory
    {
        return AttemptAnswerFactory::new();
    }
}
