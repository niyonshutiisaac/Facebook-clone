<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Friendship extends Model
{
    /** @use HasFactory<\Database\Factories\FriendshipFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id', 'friend_id', 'closeness_score', 'is_close_friend',
        'is_acquaintance', 'is_restricted', 'is_following', 'is_see_first',
        'muted_until', 'became_friends_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'closeness_score' => 'integer',
            'is_close_friend' => 'boolean',
            'is_acquaintance' => 'boolean',
            'is_restricted' => 'boolean',
            'is_following' => 'boolean',
            'is_see_first' => 'boolean',
            'muted_until' => 'datetime',
            'became_friends_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function friend(): BelongsTo
    {
        return $this->belongsTo(User::class, 'friend_id');
    }

    /**
     * Sources that should contribute posts to a user's feed: people they
     * follow, excluding anyone currently snoozed.
     *
     * @param  Builder<Friendship>  $query
     * @return Builder<Friendship>
     */
    public function scopeFeedSources(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id)
            ->where('is_following', true)
            ->where(fn (Builder $q) => $q->whereNull('muted_until')->orWhere('muted_until', '<', now()));
    }

    /**
     * @param  Builder<Friendship>  $query
     * @return Builder<Friendship>
     */
    public function scopeBetween(Builder $query, User $a, User $b): Builder
    {
        return $query->where(fn (Builder $q) => $q->where('user_id', $a->id)->where('friend_id', $b->id))
            ->orWhere(fn (Builder $q) => $q->where('user_id', $b->id)->where('friend_id', $a->id));
    }
}
