<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhotoTag extends Model
{
    /** @use HasFactory<\Database\Factories\PhotoTagFactory> */
    use HasFactory;

    protected $table = 'photo_tags';

    /**
     * @var list<string>
     */
    protected $fillable = ['photo_id', 'tagged_user_id', 'tagger_id', 'x_position', 'y_position', 'status'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'x_position' => 'decimal:2',
            'y_position' => 'decimal:2',
        ];
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }

    public function tagged(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tagged_user_id');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
