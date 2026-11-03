<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SavedCollection extends Model
{
    /** @use HasFactory<\Database\Factories\SavedCollectionFactory> */
    use HasFactory;

    protected $table = 'saved_collections';

    /**
     * @var list<string>
     */
    protected $fillable = ['user_id', 'name', 'cover_path'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SavedPost::class, 'collection_id');
    }
}
