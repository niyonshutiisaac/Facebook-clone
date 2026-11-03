<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FriendList extends Model
{
    /** @use HasFactory<\Database\Factories\FriendListFactory> */
    use HasFactory;

    protected $table = 'friend_lists';

    /**
     * @var list<string>
     */
    protected $fillable = ['user_id', 'name', 'type', 'is_default'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'friend_list_members', 'friend_list_id', 'member_id')
            ->withTimestamps();
    }
}
