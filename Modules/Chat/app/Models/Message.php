<?php

namespace Modules\Chat\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Chat\Database\Factories\MessageFactory;

/**
 * Message appartenant à une conversation directe.
 */
class Message extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'chat_messages';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'conversation_id',
        'sender_id',
        'body',
        'read_at',
        'is_crisis',
        'crisis_keywords',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'is_crisis' => 'boolean',
            'crisis_keywords' => 'array',
        ];
    }

    protected static function newFactory(): MessageFactory
    {
        return MessageFactory::new();
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ChatReport::class, 'message_id');
    }
}
