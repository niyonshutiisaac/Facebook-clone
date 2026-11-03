<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReelView extends Model
{
    /** @use HasFactory<\Database\Factories\ReelViewFactory> */
    use HasFactory;

    protected $table = 'reel_views';

    /**
     * @var list<string>
     */
    protected $fillable = ['reel_id', 'user_id', 'watched_seconds', 'completion_rate', 'replays'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'watched_seconds' => 'integer',
            'completion_rate' => 'decimal:4',
            'replays' => 'integer',
        ];
    }

    public function reel(): BelongsTo
    {
        return $this->belongsTo(Reel::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
