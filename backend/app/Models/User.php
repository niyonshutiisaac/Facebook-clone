<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccountStatus;
use App\Traits\HasMedia;
use App\Traits\TracksActivity;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens;
    use HasFactory;
    use HasMedia;
    use Notifiable;
    use SoftDeletes;
    use TracksActivity;

    /**
     * Primary keys are ordered UUIDs so identifiers are never enumerable and
     * rows can be generated client side before the server confirms them.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['id'];
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'birth_date',
        'gender',
        'pronoun',
        'avatar_path',
        'cover_path',
        'bio',
        'account_status',
        'is_verified',
        'is_profile_locked',
        'locale',
        'timezone',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'birth_date' => 'date:Y-m-d',
            'last_seen_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'password' => 'hashed',
            'is_verified' => 'boolean',
            'is_profile_locked' => 'boolean',
            'is_online' => 'boolean',
        ];
    }

    // -------------------------------------------------------------- accessors

    public function name(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    /**
     * Letters rendered by the avatar fallback when there is no profile photo.
     */
    public function initials(): string
    {
        return Str::upper(
            Str::substr((string) $this->first_name, 0, 1).Str::substr((string) $this->last_name, 0, 1)
        );
    }

    public function age(): int
    {
        return $this->birth_date === null ? 0 : (int) $this->birth_date->diffInYears(now());
    }

    public function accountStatus(): AccountStatus
    {
        return AccountStatus::tryFrom((string) $this->account_status) ?? AccountStatus::Active;
    }

    public function canAuthenticate(): bool
    {
        return $this->accountStatus()->canAuthenticate();
    }

    /**
     * Profile URLs are /{username}, falling back to the UUID when the user has
     * not claimed a username.
     */
    public function getRouteKeyName(): string
    {
        return 'username';
    }

    // ---------------------------------------------------------- relationships

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function settings(): HasOne
    {
        return $this->hasOne(UserSetting::class);
    }

    public function privacySettings(): HasMany
    {
        return $this->hasMany(UserPrivacySetting::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(Reaction::class);
    }

    public function stories(): HasMany
    {
        return $this->hasMany(Story::class);
    }

    public function reels(): HasMany
    {
        return $this->hasMany(Reel::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }

    public function videos(): HasMany
    {
        return $this->hasMany(Video::class);
    }

    public function albums(): HasMany
    {
        return $this->hasMany(PhotoAlbum::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(UserSession::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(UserDevice::class);
    }

    public function friendships(): HasMany
    {
        return $this->hasMany(Friendship::class);
    }

    public function inverseFriendships(): HasMany
    {
        return $this->hasMany(Friendship::class, 'friend_id');
    }

    public function sentFriendRequests(): HasMany
    {
        return $this->hasMany(FriendRequest::class, 'sender_id');
    }

    public function receivedFriendRequests(): HasMany
    {
        return $this->hasMany(FriendRequest::class, 'recipient_id');
    }

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_participants')
            ->withPivot([
                'role', 'nickname', 'last_read_at', 'last_read_message_id',
                'is_muted', 'is_pinned', 'is_archived', 'is_left',
            ])
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_members')
            ->withPivot(['role', 'status', 'joined_at', 'receives_notifications'])
            ->withTimestamps();
    }

    public function ownedGroups(): HasMany
    {
        return $this->hasMany(Group::class, 'owner_id');
    }

    public function pages(): BelongsToMany
    {
        return $this->belongsToMany(Page::class, 'page_roles')
            ->withPivot(['role', 'permissions'])
            ->withTimestamps();
    }

    public function ownedPages(): HasMany
    {
        return $this->hasMany(Page::class, 'owner_id');
    }

    public function hostedEvents(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function savedPosts(): HasMany
    {
        return $this->hasMany(SavedPost::class);
    }

    public function savedCollections(): HasMany
    {
        return $this->hasMany(SavedCollection::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(UserInteraction::class);
    }

    public function memories(): HasMany
    {
        return $this->hasMany(Memory::class);
    }

    // ----------------------------------------------------------------- scopes

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<User>  $query
     * @return \Illuminate\Database\Eloquent\Builder<User>
     */
    public function scopeActive($query)
    {
        return $query->where('account_status', AccountStatus::Active->value);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<User>  $query
     * @return \Illuminate\Database\Eloquent\Builder<User>
     */
    public function scopeSearch($query, string $term)
    {
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(
            fn ($q) => $q->where('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhere('username', 'like', $like)
                ->orWhere('email', 'like', $like)
        );
    }

    // -------------------------------------------------------------- graph ops

    public function isFriendWith(self $other): bool
    {
        return Friendship::query()
            ->where(fn ($q) => $q->where('user_id', $this->id)->where('friend_id', $other->id))
            ->orWhere(fn ($q) => $q->where('user_id', $other->id)->where('friend_id', $this->id))
            ->exists();
    }

    /**
     * Whether `$viewer` follows this user (a one-way edge, unlike friendship).
     */
    public function isFollowedBy(self $viewer): bool
    {
        if ($viewer->id === $this->id) {
            return false;
        }

        return Friendship::query()
            ->where('user_id', $viewer->id)
            ->where('friend_id', $this->id)
            ->where('is_following', true)
            ->exists();
    }

    /**
     * @return list<string>
     */
    public function friendIds(): array
    {
        return Friendship::query()
            ->where('user_id', $this->id)
            ->orWhere('friend_id', $this->id)
            ->get(['user_id', 'friend_id'])
            ->map(fn (Friendship $f): string => $f->user_id === $this->id ? $f->friend_id : $f->user_id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, User>
     */
    public function friends(): Collection
    {
        return static::whereIn('id', $this->friendIds())->get();
    }

    /**
     * @return list<string>
     */
    public function mutualFriendIds(self $other): array
    {
        return array_values(array_intersect($this->friendIds(), $other->friendIds()));
    }

    /**
     * @return list<string>
     */
    public function blockedIds(): array
    {
        return DB::table('blocked_users')->where('user_id', $this->id)->pluck('blocked_id')->all();
    }

    public function hasBlocked(self $other): bool
    {
        return in_array($other->id, $this->blockedIds(), true);
    }
}
