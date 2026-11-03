<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommentMedia extends Model
{
    /** @use HasFactory<\Database\Factories\CommentMediaFactory> */
    use HasFactory;

    protected $table = 'comment_media';

    /**
     * @var list<string>
     */
    protected $fillable = ['comment_id', 'type', 'path', 'disk', 'thumbnail_path', 'mime_type', 'size'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class);
    }

    protected function url(): Attribute
    {
        return Attribute::get(fn (): string => MediaUrl::image($this->path, 'image', (string) $this->comment_id));
    }
}
