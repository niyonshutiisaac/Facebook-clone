<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlockedUser extends Model
{
    /** @use HasFactory<\Database\Factories\BlockedUserFactory> */
    use HasFactory;

    protected $table = 'blocked_users';

    /**
     * @var list<string>
     */
    protected $fillable = ['user_id', 'blocked_id', 'reason'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function blocked(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_id');
    }
}
