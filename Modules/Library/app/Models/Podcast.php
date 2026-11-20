<?php

namespace Modules\Library\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Library\Database\Factories\PodcastFactory;
use Modules\Library\Enums\CompressionStatus;
use Modules\Library\Jobs\CompressPodcastAudio;

#[UseFactory(PodcastFactory::class)]
class Podcast extends Model
{
    protected $table = 'library_podcasts';

    use HasFactory;

    protected $attributes = [
        'compression_status' => 'pending',
        'is_published' => false,
    ];

    protected $fillable = [
        'subject_id',
        'level_id',
        'title',
        'description',
        'original_path',
        'compressed_path',
        'compression_status',
        'compression_error',
        'mime_type',
        'size_bytes',
        'duration_seconds',
        'uploaded_by',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'compression_status' => CompressionStatus::class,
            'is_published' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Podcast $podcast): void {
            if ($podcast->original_path) {
                CompressPodcastAudio::dispatch($podcast->id);
            }
        });

        static::updated(function (Podcast $podcast): void {
            if ($podcast->wasChanged('original_path') && $podcast->original_path) {
                $podcast->forceFill([
                    'compressed_path' => null,
                    'compression_status' => CompressionStatus::Pending,
                    'compression_error' => null,
                ])->saveQuietly();

                CompressPodcastAudio::dispatch($podcast->id);
            }
        });
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class, 'level_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function favorites(): MorphMany
    {
        return $this->morphMany(Favorite::class, 'favoritable');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function audioPath(): string
    {
        return $this->compressed_path ?: $this->original_path;
    }
}
