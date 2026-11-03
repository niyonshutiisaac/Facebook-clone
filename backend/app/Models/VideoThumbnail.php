<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoThumbnail extends Model
{
    /** @use HasFactory<\Database\Factories\VideoThumbnailFactory> */
    use HasFactory;

    protected $table = 'video_thumbnails';

    /**
     * @var list<string>
     */
    protected $fillable = ['video_id', 'path', 'time_offset', 'is_default'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'time_offset' => 'integer',
            'is_default' => 'boolean',
        ];
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }
}
