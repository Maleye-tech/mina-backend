<?php

namespace Modules\Events\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Events\Database\Factories\EventFactory;
use Modules\Events\Enums\EventType;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'type',
        'description',
        'presenter_name',
        'starts_at',
        'ends_at',
        'location_or_link',
        'max_participants',
        'current_participants',
        'replay_url',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'type' => EventType::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'max_participants' => 'integer',
            'current_participants' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    protected static function newFactory(): EventFactory
    {
        return EventFactory::new();
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class, 'event_id');
    }

    public function isUserRegistered(User $user): bool
    {
        return $this->registrations()
            ->where('user_id', $user->id)
            ->where('status', 'registered')
            ->exists();
    }

    public function isFull(): bool
    {
        return $this->max_participants !== null && $this->current_participants >= $this->max_participants;
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('starts_at', '>=', now());
    }

    public function scopePast(Builder $query): Builder
    {
        return $query->where('ends_at', '<', now());
    }
}
