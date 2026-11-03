<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoryMedia extends Model
{
    /** @use HasFactory<\Database\Factories\StoryMediaFactory> */
    use HasFactory;

    protected $table = 'story_media';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'story_id', 'type', 'path', 'disk', 'thumbnail_path',
        'mime_type', 'size', 'width', 'height', 'duration',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'duration' => 'integer',
        ];
    }

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    protected function url(): Attribute
    {
        return Attribute::get(fn (): string => MediaUrl::image($this->path, 'image', (string) $this->story_id));
    }
}
