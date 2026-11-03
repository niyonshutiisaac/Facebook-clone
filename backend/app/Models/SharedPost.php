<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SharedPost extends Model
{
    /** @use HasFactory<\Database\Factories\SharedPostFactory> */
    use HasFactory;

    protected $table = 'shared_posts';

    /**
     * @var list<string>
     */
    protected $fillable = ['post_id', 'user_id', 'group_id', 'page_id', 'audience', 'comment'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
