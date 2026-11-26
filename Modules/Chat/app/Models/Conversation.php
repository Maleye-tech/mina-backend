<?php

namespace Modules\Chat\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Appointments\Models\Appointment;
use Modules\Chat\Database\Factories\ConversationFactory;

class Conversation extends Model
{
    use HasFactory;

    protected $table = 'chat_conversations';

    protected $fillable = [
        'participant_one_id',
        'participant_two_id',
        'appointment_id',
        'last_message_at',
        'is_with_ambassador',
        'crisis_flagged_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'is_with_ambassador' => 'boolean',
            'crisis_flagged_at' => 'datetime',
        ];
    }

    protected static function newFactory(): ConversationFactory
    {
        return ConversationFactory::new();
    }

    public function participantOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'participant_one_id');
    }

    public function participantTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'participant_two_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'conversation_id');
    }

    public function lastMessage(): HasOne
    {
        return $this->hasOne(Message::class, 'conversation_id')->latestOfMany();
    }

    public function otherParticipant(User $user): User
    {
        return $this->participant_one_id === $user->id
            ? $this->participantTwo
            : $this->participantOne;
    }

    public function hasParticipant(User $user): bool
    {
        return $this->participant_one_id === $user->id
            || $this->participant_two_id === $user->id;
    }

    public function unreadCountFor(User $user): int
    {
        return $this->messages()
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->count();
    }
}
