<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageReaction extends Model
{
    /** @use HasFactory<\Database\Factories\MessageReactionFactory> */
    use HasFactory;

    protected $table = 'message_reactions';

    /**
     * @var list<string>
     */
    protected $fillable = ['message_id', 'user_id', 'reaction'];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function typeEnum(): ReactionType
    {
        return ReactionType::tryFrom((string) $this->reaction) ?? ReactionType::Like;
    }
}
