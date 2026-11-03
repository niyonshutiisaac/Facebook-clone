<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PostMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PostMedia
 */
final class PostMediaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'url' => $this->url,
            'thumbnail_url' => $this->thumbnail_url,
            'width' => $this->width,
            'height' => $this->height,
            'aspect_ratio' => $this->aspectRatio(),
            'duration' => $this->duration,
            'alt_text' => $this->alt_text,
            'position' => (int) $this->position,
            'variants' => $this->variants,
        ];
    }
}
