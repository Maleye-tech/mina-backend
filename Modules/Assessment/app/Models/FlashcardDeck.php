<?php

namespace Modules\Assessment\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Assessment\Database\Factories\FlashcardDeckFactory;
use Modules\Assessment\Enums\ContentStatus;
use Modules\Library\Models\Level;
use Modules\Library\Models\Subject;

class FlashcardDeck extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'assessment_flashcard_decks';

    protected $fillable = [
        'name',
        'description',
        'subject_id',
        'level_id',
        'status',
        'sort_order',
    ];

    protected $attributes = [
        'status' => 'draft',
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => ContentStatus::class,
            'sort_order' => 'integer',
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

    public function flashcards(): HasMany
    {
        return $this->hasMany(Flashcard::class, 'deck_id')->orderBy('sort_order');
    }

    /**
     * Uniquement les decks publiés.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ContentStatus::Published);
    }

    protected static function newFactory(): FlashcardDeckFactory
    {
        return FlashcardDeckFactory::new();
    }
}
