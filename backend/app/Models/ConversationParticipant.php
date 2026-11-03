<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationParticipant extends Model
{
    /** @use HasFactory<\Database\Factories\ConversationParticipantFactory> */
    use HasFactory;

    protected $table = 'conversation_participants';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'conversation_id', 'user_id', 'role', 'nickname', 'joined_at',
        'last_read_at', 'last_read_message_id', 'is_muted', 'is_pinned',
        'is_archived', 'is_left', 'is_typing', 'typing_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'last_read_at' => 'datetime',
            'typing_at' => 'datetime',
            'is_muted' => 'boolean',
            'is_pinned' => 'boolean',
            'is_archived' => 'boolean',
            'is_left' => 'boolean',
            'is_typing' => 'boolean',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Typing flags self-expire: a client that never sends "stop typing" (closed
     * tab, lost connection) must not leave the indicator on forever.
     *
     * @param  Builder<ConversationParticipant>  $query
     * @return Builder<ConversationParticipant>
     */
    public function scopeTyping(Builder $query): Builder
    {
        return $query->where('is_typing', true)
            ->where('typing_at', '>=', now()->subSeconds(10));
    }
}
