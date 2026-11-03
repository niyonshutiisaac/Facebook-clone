<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedPost extends Model
{
    /** @use HasFactory<\Database\Factories\SavedPostFactory> */
    use HasFactory;

    protected $table = 'saved_posts';

    /**
     * @var list<string>
     */
    protected $fillable = ['user_id', 'post_id', 'collection_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(SavedCollection::class, 'collection_id');
    }
}
