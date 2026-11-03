<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageMedia extends Model
{
    /** @use HasFactory<\Database\Factories\MessageMediaFactory> */
    use HasFactory;

    protected $table = 'message_media';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'message_id', 'type', 'path', 'disk', 'thumbnail_path', 'original_name',
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

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    protected function url(): Attribute
    {
        return Attribute::get(fn (): string => MediaUrl::image($this->path, 'image', (string) $this->message_id));
    }

    protected function thumbnailUrl(): Attribute
    {
        return Attribute::get(fn (): string => MediaUrl::image(
            $this->thumbnail_path ?? $this->path, 'image', (string) $this->message_id
        ));
    }
}
