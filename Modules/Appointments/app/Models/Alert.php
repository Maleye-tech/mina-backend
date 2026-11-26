<?php

namespace Modules\Appointments\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Appointments\Database\Factories\AlertFactory;

class Alert extends Model
{
    use HasFactory;

    protected $table = 'alerts';

    protected $fillable = [
        'user_id',
        'type',
        'severity',
        'title',
        'message',
        'status',
        'subject_type',
        'subject_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function newFactory(): AlertFactory
    {
        return AlertFactory::new();
    }
}
