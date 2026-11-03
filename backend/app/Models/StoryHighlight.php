<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class StoryHighlight extends Model
{
    /** @use HasFactory<\Database\Factories\StoryHighlightFactory> */
    use HasFactory;

    protected $table = 'story_highlights';

    /**
     * @var list<string>
     */
    protected $fillable = ['user_id', 'title', 'cover_path', 'position'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function stories(): BelongsToMany
    {
        return $this->belongsToMany(Story::class, 'story_highlight_items', 'highlight_id', 'story_id')
            ->withPivot('position')
            ->withTimestamps()
            ->orderByPivot('position');
    }
}
