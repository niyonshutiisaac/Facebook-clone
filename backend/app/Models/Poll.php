<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Poll extends Model
{
    /** @use HasFactory<\Database\Factories\PollFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = ['post_id', 'question', 'allows_multiple', 'closes_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allows_multiple' => 'boolean',
            'closes_at' => 'datetime',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(PollOption::class)->orderBy('position');
    }

    public function isOpen(): bool
    {
        return $this->closes_at === null || $this->closes_at->isFuture();
    }

    public function totalVotes(): int
    {
        return (int) $this->options()->sum('votes_count');
    }
}
