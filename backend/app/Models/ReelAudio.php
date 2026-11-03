<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReelAudio extends Model
{
    /** @use HasFactory<\Database\Factories\ReelAudioFactory> */
    use HasFactory;

    protected $table = 'reel_audio';

    /**
     * @var list<string>
     */
    protected $fillable = ['title', 'artist', 'path', 'duration', 'cover_path', 'uses_count', 'is_trending'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration' => 'integer',
            'uses_count' => 'integer',
            'is_trending' => 'boolean',
        ];
    }

    public function reels(): HasMany
    {
        return $this->hasMany(Reel::class, 'audio_id');
    }
}
