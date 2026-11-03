<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostLinkPreview extends Model
{
    /** @use HasFactory<\Database\Factories\PostLinkPreviewFactory> */
    use HasFactory;

    protected $table = 'post_link_previews';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'post_link_id', 'title', 'description', 'image_url',
        'site_name', 'favicon_url', 'canonical_url', 'fetched_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['fetched_at' => 'datetime'];
    }

    public function link(): BelongsTo
    {
        return $this->belongsTo(PostLink::class, 'post_link_id');
    }
}
