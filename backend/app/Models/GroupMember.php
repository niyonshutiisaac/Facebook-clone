<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupMember extends Model
{
    /** @use HasFactory<\Database\Factories\GroupMemberFactory> */
    use HasFactory;

    protected $table = 'group_members';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'group_id', 'user_id', 'role', 'status', 'answers',
        'joined_at', 'receives_notifications', 'badge',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'joined_at' => 'datetime',
            'receives_notifications' => 'boolean',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isModerator(): bool
    {
        return in_array($this->role, ['admin', 'moderator'], true);
    }

    /**
     * @param  Builder<GroupMember>  $query
     * @return Builder<GroupMember>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * @param  Builder<GroupMember>  $query
     * @return Builder<GroupMember>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }
}
