<?php

namespace Modules\Appointments\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Appointments\Enums\ProfessionalSpecialty;

class ProfessionalProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'display_name',
        'specialty',
        'languages',
        'bio',
        'photo_path',
        'is_verified',
        'charter_accepted_at',
    ];

    protected $casts = [
        'specialty' => ProfessionalSpecialty::class,
        'languages' => 'array',
        'is_verified' => 'boolean',
        'charter_accepted_at' => 'datetime',
    ];

    protected $attributes = [
        'is_verified' => false,
        'languages' => '["Français"]',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function availabilitySlots(): HasMany
    {
        return $this->hasMany(AvailabilitySlot::class, 'professional_id', 'user_id');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'professional_user_id', 'user_id');
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('is_verified', true)
            ->whereNotNull('charter_accepted_at');
    }

    public function hasAcceptedCharter(): bool
    {
        return $this->charter_accepted_at !== null;
    }
}
