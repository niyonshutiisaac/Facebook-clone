<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsFeedSeen extends Model
{
    /** @use HasFactory<\Database\Factories\NewsFeedSeenFactory> */
    use HasFactory;

    protected $table = 'newsfeed_seen';

    /**
     * @var list<string>
     */
    protected $fillable = ['user_id', 'post_id', 'impressions', 'dwell_ms', 'last_seen_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'impressions' => 'integer',
            'dwell_ms' => 'integer',
            'last_seen_at' => 'datetime',
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
