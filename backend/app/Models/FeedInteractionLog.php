<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Raw affinity stream. Every view, dwell, like, comment and hide writes here;
 * UpdateFeedRankingScores aggregates it into `user_interactions` and
 * `friendships.closeness_score`.
 */
class FeedInteractionLog extends Model
{
    /** @use HasFactory<\Database\Factories\FeedInteractionLogFactory> */
    use HasFactory;

    protected $table = 'feed_interaction_logs';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id', 'post_id', 'subject_id', 'subject_type',
        'action', 'weight', 'duration_ms', 'meta', 'occurred_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weight' => 'integer',
            'duration_ms' => 'integer',
            'meta' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * Per-action weights. Dwell is scaled by the caller because a 30 second
     * read is worth far more than a 200ms scroll past.
     */
    public const WEIGHTS = [
        'view' => 1,
        'dwell' => 2,
        'like' => 5,
        'comment' => 8,
        'share' => 10,
        'click' => 3,
        'profile_view' => 4,
        'hide' => -10,
        'unfollow' => -15,
    ];

    public static function weightFor(string $action): int
    {
        return self::WEIGHTS[$action] ?? 1;
    }
}
