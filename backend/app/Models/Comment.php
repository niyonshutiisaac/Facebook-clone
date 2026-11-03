<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasReactions;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comment extends Model
{
    /** @use HasFactory<\Database\Factories\CommentFactory> */
    use HasFactory;
    use HasReactions;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'post_id', 'user_id', 'parent_id', 'body', 'depth', 'path',
        'replies_count', 'reactions_count', 'is_edited', 'is_hidden',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'depth' => 'integer',
            'replies_count' => 'integer',
            'reactions_count' => 'integer',
            'is_edited' => 'boolean',
            'is_hidden' => 'boolean',
        ];
    }

    /**
     * Replies are collapsed in the UI until the user asks for them, so the
     * feed only ever loads one level eagerly.
     */
    protected function isReply(): Attribute
    {
        return Attribute::get(fn (): bool => $this->parent_id !== null);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('created_at');
    }

    public function media(): HasMany
    {
        return $this->hasMany(CommentMedia::class);
    }

    public function mentions(): HasMany
    {
        return $this->hasMany(CommentMention::class);
    }

    /**
     * @return MorphMany<Reaction>
     */
    public function reactions(): MorphMany
    {
        return $this->morphMany(Reaction::class, 'reactable');
    }

    public function recountReplies(): void
    {
        $this->forceFill(['replies_count' => $this->replies()->count()])->saveQuietly();
    }
}
