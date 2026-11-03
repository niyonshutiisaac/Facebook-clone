<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostMention extends Model
{
    /** @use HasFactory<\Database\Factories\PostMentionFactory> */
    use HasFactory;

    protected $table = 'post_mentions';

    /**
     * @var list<string>
     */
    protected $fillable = ['post_id', 'mentioned_user_id', 'offset', 'length'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function mentioned(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentioned_user_id');
    }
}
