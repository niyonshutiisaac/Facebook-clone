<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommentMention extends Model
{
    /** @use HasFactory<\Database\Factories\CommentMentionFactory> */
    use HasFactory;

    protected $table = 'comment_mentions';

    /**
     * @var list<string>
     */
    protected $fillable = ['comment_id', 'mentioned_user_id'];

    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class);
    }

    public function mentioned(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentioned_user_id');
    }
}
