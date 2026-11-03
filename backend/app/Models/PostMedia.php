<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostMedia extends Model
{
    /** @use HasFactory<\Database\Factories\PostMediaFactory> */
    use HasFactory;

    protected $table = 'post_media';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'post_id', 'type', 'path', 'disk', 'thumbnail_path', 'original_name',
        'mime_type', 'size', 'width', 'height', 'duration', 'alt_text', 'position', 'variants',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'duration' => 'integer',
            'position' => 'integer',
            'variants' => 'array',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    protected function url(): Attribute
    {
        return Attribute::get(fn (): string => MediaUrl::image($this->path, 'image', (string) $this->post_id));
    }

    protected function thumbnailUrl(): Attribute
    {
        return Attribute::get(fn (): string => MediaUrl::image(
            $this->thumbnail_path ?? $this->path, 'image', (string) $this->post_id
        ));
    }

    /**
     * Aspect ratio is used by the photo grid to reserve layout space before the
     * image loads, which keeps the feed from jumping while scrolling.
     */
    public function aspectRatio(): float
    {
        return ($this->width !== null && $this->height !== null && $this->height > 0)
            ? round($this->width / $this->height, 4)
            : 1.0;
    }
}
