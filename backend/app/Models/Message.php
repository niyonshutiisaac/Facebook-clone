<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    /** @use HasFactory<\Database\Factories\MessageFactory> */
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'conversation_id', 'sender_id', 'reply_to_id', 'forwarded_from_id',
        'type', 'body', 'delivery_status', 'delivered_at', 'read_at',
        'edited_at', 'deleted_for_everyone_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'delivery_status' => 'integer',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'edited_at' => 'datetime',
            'deleted_for_everyone_at' => 'datetime',
        ];
    }

    public function typeEnum(): MessageType
    {
        return MessageType::tryFrom((string) $this->type) ?? MessageType::Text;
    }

    public function deliveryStatus(): MessageDeliveryStatus
    {
        return MessageDeliveryStatus::tryFrom((int) $this->delivery_status) ?? MessageDeliveryStatus::Pending;
    }

    /**
     * A message is a bare "big emoji" when it is 3 characters or fewer and
     * contains only emoji — the UI then renders it without a bubble.
     */
    public function isEmojiOnly(): bool
    {
        if ($this->body === null || mb_strlen(trim($this->body)) > 8) {
            return false;
        }

        $stripped = preg_replace('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}\s]/u', '', $this->body);

        return $stripped === '';
    }

    // ---------------------------------------------------------- relationships

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    public function forwardedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'forwarded_from_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(MessageMedia::class);
    }

    public function primaryMedia(): HasOne
    {
        return $this->hasOne(MessageMedia::class);
    }

    public function reads(): HasMany
    {
        return $this->hasMany(MessageRead::class);
    }

    public function reactionRecords(): HasMany
    {
        return $this->hasMany(MessageReaction::class);
    }

    public function deletions(): HasMany
    {
        return $this->hasMany(MessageDeletion::class);
    }

    /**
     * Has this user removed the message from their own view?
     */
    public function isDeletedFor(User $user): bool
    {
        return $this->deleted_for_everyone_at !== null
            || $this->deletions()->where('user_id', $user->id)->exists();
    }

    /**
     * @return Collection<int, User>
     */
    public function readBy(): Collection
    {
        return $this->reads()->with('user')->get()->pluck('user')->filter()->values();
    }

    // --------------------------------------------------------------- mutation

    public function markDelivered(): void
    {
        if ($this->delivery_status >= MessageDeliveryStatus::Delivered->value) {
            return;
        }

        $this->forceFill([
            'delivery_status' => MessageDeliveryStatus::Delivered->value,
            'delivered_at' => now(),
        ])->save();
    }

    public function markRead(User $reader): void
    {
        $this->reads()->firstOrCreate(
            ['user_id' => $reader->id],
            ['read_at' => now()]
        );

        // Once every non-sender has read it, the message itself is "read".
        $participants = $this->conversation
            ? $this->conversation->participantRecords()->where('is_left', false)->pluck('user_id')
            : collect();

        $readers = $this->reads()->pluck('user_id');
        $outstanding = $participants->reject(fn (string $id): bool => $id === $this->sender_id)
            ->diff($readers);

        if ($outstanding->isEmpty() && $this->read_at === null) {
            $this->forceFill([
                'delivery_status' => MessageDeliveryStatus::Read->value,
                'read_at' => now(),
            ])->save();
        }
    }
}
