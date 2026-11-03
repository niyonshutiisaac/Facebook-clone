<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReelComment extends Model
{
    /** @use HasFactory<\Database\Factories\ReelCommentFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $table = 'reel_comments';

    /**
     * @var list<string>
     */
    protected $fillable = ['reel_id', 'user_id', 'parent_id', 'body', 'likes_count'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['likes_count' => 'integer'];
    }

    public function reel(): BelongsTo
    {
        return $this->belongsTo(Reel::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
