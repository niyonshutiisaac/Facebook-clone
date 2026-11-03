<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FriendListMember extends Model
{
    /** @use HasFactory<\Database\Factories\FriendListMemberFactory> */
    use HasFactory;

    protected $table = 'friend_list_members';

    /**
     * @var list<string>
     */
    protected $fillable = ['friend_list_id', 'member_id'];

    public function list(): BelongsTo
    {
        return $this->belongsTo(FriendList::class, 'friend_list_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id');
    }
}
