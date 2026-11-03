<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoryViewer extends Model
{
    /** @use HasFactory<\Database\Factories\StoryViewerFactory> */
    use HasFactory;

    protected $table = 'story_viewers';

    /**
     * @var list<string>
     */
    protected $fillable = ['story_id', 'viewer_id', 'viewed_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['viewed_at' => 'datetime'];
    }

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    public function viewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'viewer_id');
    }
}
