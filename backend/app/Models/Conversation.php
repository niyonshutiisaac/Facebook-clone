<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConversationType;
use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Conversation extends Model
{
    /** @use HasFactory<\Database\Factories\ConversationFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'type', 'pair_key', 'title', 'creator_id', 'avatar_path', 'theme_color',
        'emoji_icon', 'is_archived', 'last_message_id', 'last_message_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_archived' => 'boolean',
            'last_message_at' => 'datetime',
        ];
    }

    public function typeEnum(): ConversationType
    {
        return ConversationType::tryFrom((string) $this->type) ?? ConversationType::Private;
    }

    // ---------------------------------------------------------- relationships

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->withPivot([
                'role', 'nickname', 'last_read_at', 'last_read_message_id',
                'is_muted', 'is_pinned', 'is_archived', 'is_left', 'is_typing', 'typing_at',
            ])
            ->withTimestamps();
    }

    public function participantRecords(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    public function lastMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'last_message_id');
    }

    // -------------------------------------------------------------- accessors

    protected function avatarUrl(): Attribute
    {
        return Attribute::get(fn (): string => MediaUrl::image($this->avatar_path, 'avatar', (string) $this->id));
    }

    /**
     * Display name: the group title, or the other participant's name in a
     * private chat (never the viewer's own name).
     */
    public function displayNameFor(User $viewer): string
    {
        if ($this->typeEnum() === ConversationType::Group) {
            return $this->title !== null && $this->title !== ''
                ? $this->title
                : $this->defaultGroupName($viewer);
        }

        $other = $this->otherParticipant($viewer);

        return $other?->name() ?? 'Facebook user';
    }

    /**
     * @return Collection<int, User>
     */
    public function activeParticipants(): Collection
    {
        return $this->participants()->wherePivot('is_left', false)->get();
    }

    public function otherParticipant(User $viewer): ?User
    {
        $pivotTable = 'conversation_participants';

        return $this->participants()
            ->wherePivot('is_left', false)
            ->where("{$pivotTable}.user_id", '!=', $viewer->id)
            ->first();
    }

    /**
     * @param  Collection<int, User>  $members
     */
    public function defaultGroupName(User $viewer, ?Collection $members = null): string
    {
        $names = ($members ?? $this->activeParticipants())
            ->reject(fn (User $u): bool => $u->id === $viewer->id)
            ->map(fn (User $u): string => $u->first_name)
            ->values();

        return match (true) {
            $names->isEmpty() => 'You',
            $names->count() === 1 => $names->first(),
            $names->count() === 2 => $names->implode(' and '),
            default => $names->take(2)->implode(', ').' +'.($names->count() - 2),
        };
    }

    /**
     * Number of messages the viewer has not read, derived from the pivot's
     * last-read pointer rather than a per-message unread flag.
     */
    public function unreadCountFor(User $viewer): int
    {
        $pivot = $this->participantRecords()->where('user_id', $viewer->id)->first();

        if ($pivot === null) {
            return 0;
        }

        $query = $this->messages()->where('sender_id', '!=', $viewer->id);

        if ($pivot->last_read_at !== null) {
            $query->where('created_at', '>', $pivot->last_read_at);
        }

        return $query->count();
    }

    /**
     * Stable private-conversation key so two users can never end up with
     * duplicate 1:1 threads.
     */
    public static function pairKey(User $a, User $b): string
    {
        $ids = [$a->id, $b->id];
        sort($ids);

        return Str::lower(implode(':', $ids));
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Conversation>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Conversation>
     */
    public function scopeBetween($query, User $a, User $b)
    {
        return $query->where('pair_key', self::pairKey($a, $b));
    }
}
