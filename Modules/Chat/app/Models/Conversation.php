<?php

namespace Modules\Chat\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Chat\Database\Factories\ConversationFactory;

/**
 * Conversation directe (1-1) entre deux utilisateurs.
 */
class Conversation extends Model
{
    use HasFactory;

    protected $table = 'chat_conversations';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'participant_one_id',
        'participant_two_id',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
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

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'conversation_id');
    }

    public function lastMessage(): HasOne
    {
        return $this->hasOne(Message::class, 'conversation_id')->latestOfMany();
    }

    /**
     * L'autre participant que l'utilisateur donné.
     */
    public function otherParticipant(User $user): User
    {
        return $this->participant_one_id === $user->id
            ? $this->participantTwo
            : $this->participantOne;
    }

    /**
     * L'utilisateur est-il participant de cette conversation ?
     */
    public function hasParticipant(User $user): bool
    {
        return $this->participant_one_id === $user->id
            || $this->participant_two_id === $user->id;
    }

    /**
     * Nombre de messages reçus et non encore lus.
     */
    public function unreadCountFor(User $user): int
    {
        return $this->messages()
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->count();
    }
}
