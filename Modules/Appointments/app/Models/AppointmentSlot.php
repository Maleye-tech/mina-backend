<?php

namespace Modules\Appointments\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Appointments\Database\Factories\AppointmentSlotFactory;

class AppointmentSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'professional_id',
        'starts_at',
        'ends_at',
        'is_booked',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_booked' => 'boolean',
        ];
    }

    protected static function newFactory(): AppointmentSlotFactory
    {
        return AppointmentSlotFactory::new();
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(AppointmentProfessional::class, 'professional_id');
    }

    public function appointment(): HasOne
    {
        return $this->hasOne(Appointment::class, 'slot_id');
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_booked', false)
            ->where('starts_at', '>', now());
    }
}
