<?php

namespace Modules\Library\Models;

use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Library\Database\Factories\ContentFactory;
use Modules\Library\Enums\ContentType;

#[UseFactory(ContentFactory::class)]
class Content extends Model
{
    protected $table = 'library_contents';

    use HasFactory;

    protected $fillable = [
        'subject_id',
        'level_id',
        'type',
        'title',
        'description',
        'body',
        'sort_order',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'type' => ContentType::class,
            'is_published' => 'boolean',
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class, 'level_id');
    }

    public function favorites(): MorphMany
    {
        return $this->morphMany(Favorite::class, 'favoritable');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }
}
