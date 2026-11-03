<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostFeeling extends Model
{
    /** @use HasFactory<\Database\Factories\PostFeelingFactory> */
    use HasFactory;

    protected $table = 'post_feelings';

    /**
     * @var list<string>
     */
    protected $fillable = ['post_id', 'kind', 'value', 'emoji'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
