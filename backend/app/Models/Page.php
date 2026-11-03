<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;

class Page extends Model
{
    /** @use HasFactory<\Database\Factories\PageFactory> */
    use HasFactory;
    use Searchable;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'owner_id', 'name', 'slug', 'category', 'description', 'website',
        'phone', 'email', 'avatar_path', 'cover_path', 'about',
        'followers_count', 'likes_count', 'posts_count', 'is_verified', 'is_published',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'about' => 'array',
            'followers_count' => 'integer',
            'likes_count' => 'integer',
            'posts_count' => 'integer',
            'is_verified' => 'boolean',
            'is_published' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $page): void {
            if ($page->slug === null || $page->slug === '') {
                $page->slug = Str::slug($page->name).'-'.Str::lower(Str::random(6));
            }
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function roles(): HasMany
    {
        return $this->hasMany(PageRole::class);
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'page_followers')
            ->withPivot('receives_notifications')
            ->withTimestamps();
    }

    public function pagePosts(): HasMany
    {
        return $this->hasMany(PagePost::class);
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'page_posts')->withTimestamps();
    }

    public function isFollowedBy(User $user): bool
    {
        return $this->followers()->where('users.id', $user->id)->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'description' => $this->description,
        ];
    }
}
