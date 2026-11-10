<?php

namespace Modules\Assessment\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Assessment\Database\Factories\FlashcardReviewFactory;

class FlashcardReview extends Model
{
    use HasFactory;

    protected $table = 'assessment_flashcard_reviews';

    protected $fillable = [
        'user_id',
        'flashcard_id',
        'quality',
        'easiness_factor',
        'interval_days',
        'due_at',
    ];

    protected function casts(): array
    {
        return [
            'quality' => 'integer',
            'easiness_factor' => 'decimal:2',
            'interval_days' => 'integer',
            'due_at' => 'datetime',
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

    protected static function newFactory(): FlashcardReviewFactory
    {
        return FlashcardReviewFactory::new();
    }
}
