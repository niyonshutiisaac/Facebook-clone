<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorySticker extends Model
{
    /** @use HasFactory<\Database\Factories\StoryStickerFactory> */
    use HasFactory;

    protected $table = 'story_stickers';

    /**
     * @var list<string>
     */
    protected $fillable = ['story_id', 'type', 'payload', 'x_position', 'y_position', 'scale', 'rotation'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'x_position' => 'decimal:2',
            'y_position' => 'decimal:2',
            'scale' => 'decimal:2',
            'rotation' => 'decimal:2',
        ];
    }

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }
}
