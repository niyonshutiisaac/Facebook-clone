<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoryReply extends Model
{
    /** @use HasFactory<\Database\Factories\StoryReplyFactory> */
    use HasFactory;

    protected $table = 'story_replies';

    /**
     * @var list<string>
     */
    protected $fillable = ['story_id', 'user_id', 'body', 'reaction', 'is_read'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_read' => 'boolean'];
    }

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
