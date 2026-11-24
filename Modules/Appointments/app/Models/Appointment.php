<?php

namespace Modules\Appointments\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Appointments\Database\Factories\AppointmentFactory;
use Modules\Appointments\Enums\AppointmentStatus;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'professional_id',
        'slot_id',
        'scheduled_at',
        'duration_minutes',
        'status',
        'consultation_type',
        'meeting_link',
        'student_notes',
        'confidential_notes',
        'cancellation_reason',
        'cancelled_by',
        'reminder_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'duration_minutes' => 'integer',
            'status' => AppointmentStatus::class,
            'student_notes' => 'encrypted',
            'confidential_notes' => 'encrypted',
            'reminder_sent_at' => 'datetime',
        ];
    }

    protected static function newFactory(): AppointmentFactory
    {
        return AppointmentFactory::new();
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(AppointmentProfessional::class, 'professional_id');
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(AppointmentSlot::class, 'slot_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
