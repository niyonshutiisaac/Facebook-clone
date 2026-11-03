<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PhotoAlbum extends Model
{
    /** @use HasFactory<\Database\Factories\PhotoAlbumFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $table = 'photo_albums';

    /**
     * @var list<string>
     */
    protected $fillable = ['user_id', 'name', 'description', 'cover_photo_id', 'audience', 'photos_count'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['photos_count' => 'integer'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class, 'album_id')->latest();
    }

    public function recountPhotos(): void
    {
        $this->forceFill(['photos_count' => $this->photos()->count()])->saveQuietly();
    }
}
