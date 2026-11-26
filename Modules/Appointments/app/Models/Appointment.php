<?php

namespace Modules\Appointments\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Appointments\Database\Factories\AppointmentFactory;
use Modules\Appointments\Enums\AppointmentStatus;
use Modules\Chat\Models\Conversation;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'professional_id',
        'professional_user_id',
        'slot_id',
        'availability_slot_id',
        'scheduled_at',
        'duration_minutes',
        'status',
        'consultation_type',
        'reason',
        'meeting_link',
        'meeting_url',
        'student_notes',
        'confidential_notes',
        'cancellation_reason',
        'cancelled_by',
        'responded_at',
        'reminder_sent_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'duration_minutes' => 'integer',
        'status' => AppointmentStatus::class,
        'student_notes' => 'encrypted',
        'confidential_notes' => 'encrypted',
        'reminder_sent_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    protected static function newFactory(): AppointmentFactory
    {
        return AppointmentFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (self $appointment): void {
            if ($appointment->professional_user_id === null && $appointment->professional_id !== null) {
                $appointment->professional_user_id = AppointmentProfessional::query()
                    ->whereKey($appointment->professional_id)
                    ->value('user_id');
            }
        });
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function professionalUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'professional_user_id');
    }

    public function professionalProfile(): BelongsTo
    {
        return $this->belongsTo(ProfessionalProfile::class, 'professional_user_id', 'user_id');
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(AppointmentProfessional::class, 'professional_id');
    }

    public function availabilitySlot(): BelongsTo
    {
        return $this->belongsTo(AvailabilitySlot::class, 'availability_slot_id');
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(AppointmentSlot::class, 'slot_id');
    }

    public function sessionNote(): HasOne
    {
        return $this->hasOne(SessionNote::class, 'appointment_id');
    }

    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class, 'appointment_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function isRequested(): bool
    {
        return $this->status === AppointmentStatus::Requested;
    }

    public function isConfirmed(): bool
    {
        return $this->status === AppointmentStatus::Confirmed;
    }

    public function isDeclined(): bool
    {
        return $this->status === AppointmentStatus::Declined;
    }

    public function isCancelled(): bool
    {
        return $this->status === AppointmentStatus::Cancelled;
    }

    public function isCompleted(): bool
    {
        return $this->status === AppointmentStatus::Completed;
    }
}
