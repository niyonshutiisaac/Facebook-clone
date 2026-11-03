<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FriendSuggestion extends Model
{
    /** @use HasFactory<\Database\Factories\FriendSuggestionFactory> */
    use HasFactory;

    protected $table = 'friend_suggestions';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id', 'suggested_user_id', 'mutual_friends_count', 'score',
        'signals', 'is_dismissed', 'dismissed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mutual_friends_count' => 'integer',
            'score' => 'decimal:4',
            'signals' => 'array',
            'is_dismissed' => 'boolean',
            'dismissed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function suggested(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suggested_user_id');
    }

    /**
     * @param  Builder<FriendSuggestion>  $query
     * @return Builder<FriendSuggestion>
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('is_dismissed', false);
    }
}
