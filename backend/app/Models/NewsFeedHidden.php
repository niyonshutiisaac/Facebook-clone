<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsFeedHidden extends Model
{
    /** @use HasFactory<\Database\Factories\NewsFeedHiddenFactory> */
    use HasFactory;

    protected $table = 'newsfeed_hidden';

    /**
     * @var list<string>
     */
    protected $fillable = ['user_id', 'post_id', 'reason'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
