<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoQuality extends Model
{
    /** @use HasFactory<\Database\Factories\VideoQualityFactory> */
    use HasFactory;

    protected $table = 'video_qualities';

    /**
     * @var list<string>
     */
    protected $fillable = ['video_id', 'label', 'height', 'bitrate_kbps', 'path', 'playlist_path', 'size'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'height' => 'integer',
            'bitrate_kbps' => 'integer',
            'size' => 'integer',
        ];
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }
}
