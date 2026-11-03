<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\HasReactions;
use Illuminate\Database\Eloquent\SoftDeletes;

class Story extends Model
{
    /** @use HasFactory<\Database\Factories\StoryFactory> */
    use HasFactory;
    use HasReactions;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id', 'type', 'body', 'background', 'audience', 'duration',
        'views_count', 'replies_count', 'reactions_count',
        'is_archived', 'is_highlighted', 'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration' => 'integer',
            'views_count' => 'integer',
            'replies_count' => 'integer',
            'reactions_count' => 'integer',
            'is_archived' => 'boolean',
            'is_highlighted' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(StoryMedia::class);
    }

    public function viewers(): HasMany
    {
        return $this->hasMany(StoryViewer::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(StoryReply::class);
    }

    public function stickers(): HasMany
    {
        return $this->hasMany(StorySticker::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany<Reaction>
     */
    public function reactions(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Reaction::class, 'reactable');
    }

    /**
     * Only stories that are still within their 24 hour window.
     *
     * @param  Builder<Story>  $query
     * @return Builder<Story>
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }

    public function hasExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
