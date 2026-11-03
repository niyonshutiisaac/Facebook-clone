<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostAudience extends Model
{
    /** @use HasFactory<\Database\Factories\PostAudienceFactory> */
    use HasFactory;

    protected $table = 'post_audiences';

    /**
     * @var list<string>
     */
    protected $fillable = ['post_id', 'user_id', 'is_allowed'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_allowed' => 'boolean'];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
