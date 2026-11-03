<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PollOption extends Model
{
    /** @use HasFactory<\Database\Factories\PollOptionFactory> */
    use HasFactory;

    protected $table = 'poll_options';

    /**
     * @var list<string>
     */
    protected $fillable = ['poll_id', 'label', 'position', 'votes_count'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'votes_count' => 'integer',
        ];
    }

    public function poll(): BelongsTo
    {
        return $this->belongsTo(Poll::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(PollVote::class);
    }

    /**
     * Share of the total vote, as a percentage rounded to one decimal.
     */
    public function percentage(): float
    {
        $total = $this->poll?->totalVotes() ?? 0;

        return $total > 0 ? round(($this->votes_count / $total) * 100, 1) : 0.0;
    }
}
