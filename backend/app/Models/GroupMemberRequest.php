<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupMemberRequest extends Model
{
    /** @use HasFactory<\Database\Factories\GroupMemberRequestFactory> */
    use HasFactory;

    protected $table = 'group_member_requests';

    /**
     * @var list<string>
     */
    protected $fillable = ['group_id', 'user_id', 'reviewed_by', 'message', 'answers', 'status', 'reviewed_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'reviewed_at' => 'datetime',
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

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
