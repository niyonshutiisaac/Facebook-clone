<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Memory extends Model
{
    /** @use HasFactory<\Database\Factories\MemoryFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id', 'post_id', 'memory_date', 'years_ago',
        'is_hidden', 'shared_at', 'last_shown_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'memory_date' => 'date:Y-m-d',
            'years_ago' => 'integer',
            'is_hidden' => 'boolean',
            'shared_at' => 'datetime',
            'last_shown_at' => 'datetime',
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
     * Memories for "this day" (anniversaries of posts made on today's
     * month/day in previous years).
     *
     * @param  Builder<Memory>  $query
     * @return Builder<Memory>
     */
    public function scopeToday(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id)
            ->where('is_hidden', false)
            ->whereMonth('memory_date', now()->month)
            ->whereDay('memory_date', now()->day)
            ->where('years_ago', '>=', 1)
            ->orderByDesc('years_ago');
    }
}
