<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CloseFriend extends Model
{
    /** @use HasFactory<\Database\Factories\CloseFriendFactory> */
    use HasFactory;

    protected $table = 'close_friends';

    /**
     * @var list<string>
     */
    protected $fillable = ['user_id', 'close_friend_id', 'notify_on_post'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['notify_on_post' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function closeFriend(): BelongsTo
    {
        return $this->belongsTo(User::class, 'close_friend_id');
    }
}
