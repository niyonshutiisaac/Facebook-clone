<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostHashtag extends Model
{
    /** @use HasFactory<\Database\Factories\PostHashtagFactory> */
    use HasFactory;

    protected $table = 'post_hashtags';

    /**
     * @var list<string>
     */
    protected $fillable = ['post_id', 'tag'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
