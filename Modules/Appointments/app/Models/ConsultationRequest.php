<?php

namespace Modules\Appointments\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Appointments\Database\Factories\ConsultationRequestFactory;

class ConsultationRequest extends Model
{
    use HasFactory;

    protected $table = 'consultation_requests';

    protected $fillable = [
        'student_id',
        'professional_id',
        'topic',
        'priority',
        'status',
        'requested_at',
        'scheduled_for',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'scheduled_for' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(AppointmentProfessional::class, 'professional_id');
    }

    public function scopeForProfessional($query, $professionalId)
    {
        return $query->where('professional_id', $professionalId);
    }

    protected static function newFactory(): ConsultationRequestFactory
    {
        return ConsultationRequestFactory::new();
    }
}
