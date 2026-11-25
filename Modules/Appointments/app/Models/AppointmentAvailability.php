<?php

namespace Modules\Appointments\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Appointments\Database\Factories\AppointmentAvailabilityFactory;

class AppointmentAvailability extends Model
{
    use HasFactory;

    protected $table = 'appointment_availabilities';

    protected $fillable = [
        'professional_id',
        'weekday',
        'start_time',
        'end_time',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(AppointmentProfessional::class, 'professional_id');
    }

    protected static function newFactory(): AppointmentAvailabilityFactory
    {
        return AppointmentAvailabilityFactory::new();
    }
}
