<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupPost extends Model
{
    /** @use HasFactory<\Database\Factories\GroupPostFactory> */
    use HasFactory;

    protected $table = 'group_posts';

    /**
     * @var list<string>
     */
    protected $fillable = ['group_id', 'post_id', 'approved_by', 'is_announcement', 'is_pinned', 'status'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_announcement' => 'boolean',
            'is_pinned' => 'boolean',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
