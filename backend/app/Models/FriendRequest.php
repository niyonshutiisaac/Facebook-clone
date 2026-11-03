<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FriendRequestStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FriendRequest extends Model
{
    /** @use HasFactory<\Database\Factories\FriendRequestFactory> */
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = ['sender_id', 'recipient_id', 'status', 'message', 'responded_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['responded_at' => 'datetime'];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function statusEnum(): FriendRequestStatus
    {
        return FriendRequestStatus::tryFrom((string) $this->status) ?? FriendRequestStatus::Pending;
    }

    public function isPending(): bool
    {
        return $this->statusEnum() === FriendRequestStatus::Pending;
    }

    /**
     * @param  Builder<FriendRequest>  $query
     * @return Builder<FriendRequest>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', FriendRequestStatus::Pending->value);
    }

    /**
     * @param  Builder<FriendRequest>  $query
     * @return Builder<FriendRequest>
     */
    public function scopeBetween(Builder $query, User $a, User $b): Builder
    {
        return $query->where(fn (Builder $q) => $q->where('sender_id', $a->id)->where('recipient_id', $b->id))
            ->orWhere(fn (Builder $q) => $q->where('sender_id', $b->id)->where('recipient_id', $a->id));
    }
}
