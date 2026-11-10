<?php

namespace Modules\Assessment\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Assessment\Database\Factories\FlashcardStateFactory;

class FlashcardState extends Model
{
    use HasFactory;

    protected $table = 'assessment_flashcard_states';

    protected $fillable = [
        'user_id',
        'flashcard_id',
        'easiness_factor',
        'interval_days',
        'repetitions',
        'lapses',
        'last_quality',
        'due_at',
        'last_reviewed_at',
    ];

    protected $attributes = [
        'easiness_factor' => 2.50,
        'interval_days' => 0,
        'repetitions' => 0,
        'lapses' => 0,
    ];

    protected function casts(): array
    {
        return [
            'easiness_factor' => 'decimal:2',
            'interval_days' => 'integer',
            'repetitions' => 'integer',
            'lapses' => 'integer',
            'last_quality' => 'integer',
            'due_at' => 'datetime',
            'last_reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function flashcard(): BelongsTo
    {
        return $this->belongsTo(Flashcard::class);
    }

    protected static function newFactory(): FlashcardStateFactory
    {
        return FlashcardStateFactory::new();
    }
}
