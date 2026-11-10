<?php

namespace Modules\Assessment\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Assessment\Database\Factories\FlashcardFactory;

class Flashcard extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'assessment_flashcards';

    protected $fillable = [
        'deck_id',
        'front',
        'back',
        'hint',
        'sort_order',
    ];

    protected $attributes = [
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function deck(): BelongsTo
    {
        return $this->belongsTo(FlashcardDeck::class, 'deck_id');
    }

    public function states(): HasMany
    {
        return $this->hasMany(FlashcardState::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(FlashcardReview::class);
    }

    protected static function newFactory(): FlashcardFactory
    {
        return FlashcardFactory::new();
    }
}
