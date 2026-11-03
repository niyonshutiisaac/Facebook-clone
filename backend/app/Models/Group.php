<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;

class Group extends Model
{
    /** @use HasFactory<\Database\Factories\GroupFactory> */
    use HasFactory;
    use Searchable;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'owner_id', 'name', 'slug', 'description', 'type', 'privacy',
        'cover_path', 'avatar_path', 'topics', 'questions',
        'requires_post_approval', 'members_count', 'posts_count', 'pending_members_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'topics' => 'array',
            'questions' => 'array',
            'requires_post_approval' => 'boolean',
            'members_count' => 'integer',
            'posts_count' => 'integer',
            'pending_members_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $group): void {
            if ($group->slug === null || $group->slug === '') {
                $group->slug = Str::slug($group->name).'-'.Str::lower(Str::random(6));
            }
        });
    }

    // ---------------------------------------------------------- relationships

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_members')
            ->withPivot(['role', 'status', 'joined_at', 'receives_notifications', 'badge'])
            ->withTimestamps();
    }

    public function membershipRecords(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }

    public function joinRequests(): HasMany
    {
        return $this->hasMany(GroupMemberRequest::class);
    }

    public function rules(): HasMany
    {
        return $this->hasMany(GroupRule::class)->orderBy('position');
    }

    public function admins(): HasMany
    {
        return $this->hasMany(GroupAdmin::class);
    }

    public function groupPosts(): HasMany
    {
        return $this->hasMany(GroupPost::class);
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'group_posts')->withTimestamps();
    }

    // ------------------------------------------------------------ predicates

    public function isPublic(): bool
    {
        return $this->privacy === 'public';
    }

    public function isHidden(): bool
    {
        return $this->privacy === 'private_hidden';
    }

    public function membershipFor(User $user): ?GroupMember
    {
        return $this->membershipRecords()->where('user_id', $user->id)->first();
    }

    public function roleOf(User $user): ?string
    {
        return $this->membershipFor($user)?->role;
    }

    public function isAdmin(User $user): bool
    {
        return $user->id === $this->owner_id || in_array($this->roleOf($user), ['admin', 'moderator'], true);
    }

    /**
     * @param  Builder<Group>  $query
     * @return Builder<Group>
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('privacy', '!=', 'private_hidden');
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'privacy' => $this->privacy,
            'members_count' => $this->members_count,
        ];
    }
}
