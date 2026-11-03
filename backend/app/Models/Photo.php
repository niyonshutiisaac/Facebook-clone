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

class Photo extends Model
{
    /** @use HasFactory<\Database\Factories\PhotoFactory> */
    use HasFactory;
    use HasPrivacy;
    use HasReactions;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id', 'album_id', 'post_id', 'path', 'disk', 'thumbnail_path',
        'original_name', 'mime_type', 'size', 'width', 'height', 'alt_text',
        'caption', 'audience', 'reactions_count', 'comments_count',
        'is_profile_picture', 'is_cover_photo', 'variants',
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
            'reactions_count' => 'integer',
            'comments_count' => 'integer',
            'is_profile_picture' => 'boolean',
            'is_cover_photo' => 'boolean',
            'variants' => 'array',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function album(): BelongsTo
    {
        return $this->belongsTo(PhotoAlbum::class, 'album_id');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function tags(): HasMany
    {
        return $this->hasMany(PhotoTag::class);
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
        return Attribute::get(fn (): string => MediaUrl::image($this->path, 'image', (string) $this->user_id));
    }

    protected function thumbnailUrl(): Attribute
    {
        return Attribute::get(fn (): string => MediaUrl::image(
            $this->thumbnail_path ?? $this->path, 'image', (string) $this->user_id
        ));
    }
}
