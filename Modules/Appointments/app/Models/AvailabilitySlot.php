<?php

namespace Modules\Appointments\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Appointments\Enums\SlotMode;

class AvailabilitySlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'professional_id',
        'starts_at',
        'ends_at',
        'mode',
        'is_booked',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'mode' => SlotMode::class,
        'is_booked' => 'boolean',
    ];

    protected $attributes = [
        'mode' => 'visio',
        'is_booked' => false,
    ];

    public function professional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'professional_id');
    }

    public function appointment(): HasOne
    {
        return $this->hasOne(Appointment::class, 'availability_slot_id');
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_booked', false)
            ->where('starts_at', '>', now());
    }

    public function scopeForProfessional(Builder $query, int $professionalId): Builder
    {
        return $query->where('professional_id', $professionalId);
    }
}
