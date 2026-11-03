<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PostLink extends Model
{
    /** @use HasFactory<\Database\Factories\PostLinkFactory> */
    use HasFactory;

    protected $table = 'post_links';

    /**
     * @var list<string>
     */
    protected $fillable = ['post_id', 'url', 'domain', 'is_preview_enabled'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_preview_enabled' => 'boolean'];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function preview(): HasOne
    {
        return $this->hasOne(PostLinkPreview::class);
    }
}
