<?php

namespace Modules\Appointments\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Appointments\Database\Factories\AppointmentProfessionalFactory;

class AppointmentProfessional extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'bio',
        'phone',
        'consultation_duration_minutes',
        'is_available',
    ];

    protected function casts(): array
    {
        return [
            'consultation_duration_minutes' => 'integer',
            'is_available' => 'boolean',
        ];
    }

    protected static function newFactory(): AppointmentProfessionalFactory
    {
        return AppointmentProfessionalFactory::new();
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
}
