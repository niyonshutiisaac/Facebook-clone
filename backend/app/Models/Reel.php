<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\MediaUrl;
use App\Traits\HasPrivacy;
use App\Traits\HasReactions;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Reel extends Model
{
    /** @use HasFactory<\Database\Factories\ReelFactory> */
    use HasFactory;
    use HasPrivacy;
    use HasReactions;
    use Searchable;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id', 'audio_id', 'path', 'disk', 'thumbnail_path', 'original_name',
        'mime_type', 'size', 'duration', 'width', 'height', 'caption',
        'audience', 'allows_remix', 'allows_comments', 'status', 'processing_error',
        'views_count', 'likes_count', 'comments_count', 'shares_count',
        'completion_rate', 'ranking_score',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'duration' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'allows_remix' => 'boolean',
            'allows_comments' => 'boolean',
            'views_count' => 'integer',
            'likes_count' => 'integer',
            'comments_count' => 'integer',
            'shares_count' => 'integer',
            'completion_rate' => 'decimal:4',
            'ranking_score' => 'decimal:6',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function audio(): BelongsTo
    {
        return $this->belongsTo(ReelAudio::class, 'audio_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ReelComment::class)->whereNull('parent_id')->latest();
    }

    public function views(): HasMany
    {
        return $this->hasMany(ReelView::class);
    }

    /**
     * @return MorphMany<Reaction>
     */
    public function reactions(): MorphMany
    {
        return $this->morphMany(Reaction::class, 'reactable');
    }

    protected function url(): Attribute
    {
        return Attribute::get(fn (): string => MediaUrl::image($this->path, 'video', (string) $this->user_id));
    }

    protected function posterUrl(): Attribute
    {
        return Attribute::get(fn (): string => MediaUrl::image($this->thumbnail_path, 'image', (string) $this->user_id));
    }

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'caption' => $this->caption,
            'created_at' => $this->created_at?->timestamp,
        ];
    }
}
