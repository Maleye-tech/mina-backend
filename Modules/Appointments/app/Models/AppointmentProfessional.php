<?php

namespace Modules\Appointments\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Appointments\Database\Factories\AppointmentProfessionalFactory;
use Modules\Appointments\Enums\ProfessionalSpecialty;

class AppointmentProfessional extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'display_name',
        'specialty',
        'languages',
        'bio',
        'photo_path',
        'phone',
        'consultation_duration_minutes',
        'is_available',
        'is_verified',
        'charter_accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'languages' => 'array',
            'consultation_duration_minutes' => 'integer',
            'is_available' => 'boolean',
            'is_verified' => 'boolean',
            'charter_accepted_at' => 'datetime',
            'specialty' => ProfessionalSpecialty::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function slots(): HasMany
    {
        return $this->hasMany(AppointmentSlot::class, 'professional_id');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'professional_id');
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_available', true);
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('is_verified', true);
    }

    public function hasAcceptedCharter(): bool
    {
        return $this->charter_accepted_at !== null;
    }

    public function canOpenSlots(): bool
    {
        return $this->is_verified && $this->hasAcceptedCharter();
    }

    protected static function newFactory(): AppointmentProfessionalFactory
    {
        return AppointmentProfessionalFactory::new();
    }
}
