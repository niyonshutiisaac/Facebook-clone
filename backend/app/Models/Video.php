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

class Video extends Model
{
    /** @use HasFactory<\Database\Factories\VideoFactory> */
    use HasFactory;
    use HasPrivacy;
    use HasReactions;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id', 'post_id', 'title', 'description', 'path', 'disk', 'thumbnail_path',
        'original_name', 'mime_type', 'size', 'duration', 'width', 'height',
        'status', 'processing_job_id', 'processing_error', 'audience',
        'views_count', 'reactions_count', 'comments_count', 'subtitles_path',
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
            'views_count' => 'integer',
            'reactions_count' => 'integer',
            'comments_count' => 'integer',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function qualities(): HasMany
    {
        return $this->hasMany(VideoQuality::class)->orderBy('height');
    }

    public function thumbnails(): HasMany
    {
        return $this->hasMany(VideoThumbnail::class)->orderBy('time_offset');
    }

    public function views(): HasMany
    {
        return $this->hasMany(VideoView::class);
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
}
