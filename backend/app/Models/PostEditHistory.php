<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostEditHistory extends Model
{
    /** @use HasFactory<\Database\Factories\PostEditHistoryFactory> */
    use HasFactory;

    protected $table = 'post_edit_history';

    /**
     * @var list<string>
     */
    protected $fillable = ['post_id', 'editor_id', 'previous_body', 'previous_audience', 'reason'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'editor_id');
    }
}
