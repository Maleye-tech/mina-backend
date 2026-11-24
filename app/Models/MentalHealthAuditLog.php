<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MentalHealthAuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'accessor_id',
        'student_id',
        'resource_type',
        'resource_id',
        'action',
        'ip_address',
        'user_agent',
        'accessed_at',
    ];

    protected function casts(): array
    {
        return [
            'accessed_at' => 'datetime',
        ];
    }

    public function accessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accessor_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public static function log(
        User $accessor,
        User $student,
        string $resourceType,
        int $resourceId,
        string $action = 'view',
        ?string $ip = null,
        ?string $userAgent = null
    ): self {
        return self::create([
            'accessor_id' => $accessor->id,
            'student_id' => $student->id,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'action' => $action,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'accessed_at' => now(),
        ]);
    }
}
