<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Durable copy of a user's ranked feed. Redis holds the hot copy; this table
 * survives a Redis flush and lets us recompute or audit a feed offline.
 */
class NewsFeedCache extends Model
{
    /** @use HasFactory<\Database\Factories\NewsFeedCacheFactory> */
    use HasFactory;

    protected $table = 'newsfeed_cache';

    /**
     * @var list<string>
     */
    protected $fillable = ['user_id', 'post_id', 'score', 'cursor', 'signals', 'computed_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'score' => 'decimal:6',
            'signals' => 'array',
            'computed_at' => 'datetime',
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
}
