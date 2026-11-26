<?php

namespace Modules\Appointments\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Appointments\Enums\AlertStatus;

class UrgentAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'appointment_id',
        'message',
        'status',
        'handled_by',
        'handled_at',
    ];

    protected $casts = [
        'message' => 'encrypted',
        'status' => AlertStatus::class,
        'handled_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'open',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', AlertStatus::Open);
    }

    public function markAsHandled(User $handler): void
    {
        $this->update([
            'status' => AlertStatus::Handled,
            'handled_by' => $handler->id,
            'handled_at' => now(),
        ]);
    }
}
