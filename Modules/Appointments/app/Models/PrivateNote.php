<?php

namespace Modules\Appointments\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Appointments\Database\Factories\PrivateNoteFactory;
use Modules\Chat\Models\Conversation;

class PrivateNote extends Model
{
    use HasFactory;

    protected $table = 'private_notes';

    protected $fillable = [
        'user_id',
        'appointment_id',
        'conversation_id',
        'body',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'body' => 'encrypted',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    protected static function newFactory(): PrivateNoteFactory
    {
        return PrivateNoteFactory::new();
    }
}
