<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserConsent extends Model
{
    public const PERSONAL_DATA = 'personal_data';

    public const MENTAL_HEALTH_DATA = 'mental_health_data';

    protected $fillable = [
        'consent_type',
        'granted',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'granted' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
