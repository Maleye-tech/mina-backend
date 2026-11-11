<?php

namespace Modules\WellBeing\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\WellBeing\Database\Factories\EmergencyContactFactory;

/**
 * Contact d'urgence validé par Volkeno et ses partenaires.
 */
class EmergencyContact extends Model
{
    use HasFactory;

    protected $table = 'emergency_contacts';

    protected $fillable = [
        'name',
        'role_or_description',
        'phone_number',
        'address',
        'availability',
        'is_active',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Uniquement les contacts actifs.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    protected static function newFactory(): EmergencyContactFactory
    {
        return EmergencyContactFactory::new();
    }
}
