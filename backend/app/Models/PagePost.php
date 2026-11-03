<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PagePost extends Model
{
    /** @use HasFactory<\Database\Factories\PagePostFactory> */
    use HasFactory;

    protected $table = 'page_posts';

    /**
     * @var list<string>
     */
    protected $fillable = ['page_id', 'post_id', 'is_pinned'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_pinned' => 'boolean'];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
