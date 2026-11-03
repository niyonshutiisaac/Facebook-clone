<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoView extends Model
{
    /** @use HasFactory<\Database\Factories\VideoViewFactory> */
    use HasFactory;

    protected $table = 'video_views';

    /**
     * @var list<string>
     */
    protected $fillable = ['video_id', 'user_id', 'watched_seconds', 'completion_rate', 'ip_address', 'user_agent'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'watched_seconds' => 'integer',
            'completion_rate' => 'decimal:4',
        ];
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
