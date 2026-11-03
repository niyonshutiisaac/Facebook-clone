<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aggregated affinity between two users.
 *
 * Recomputed on a schedule from `feed_interaction_logs` so the feed ranking
 * model can score candidate posts without touching the raw log at read time.
 */
class UserInteraction extends Model
{
    /** @use HasFactory<\Database\Factories\UserInteractionFactory> */
    use HasFactory;

    protected $table = 'user_interactions';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id', 'target_user_id', 'profile_views', 'messages_sent',
        'reactions_given', 'comments_written', 'posts_viewed', 'dwell_ms',
        'photo_tags', 'affinity_score', 'last_interaction_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'profile_views' => 'integer',
            'messages_sent' => 'integer',
            'reactions_given' => 'integer',
            'comments_written' => 'integer',
            'posts_viewed' => 'integer',
            'dwell_ms' => 'integer',
            'photo_tags' => 'integer',
            'affinity_score' => 'decimal:4',
            'last_interaction_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}
