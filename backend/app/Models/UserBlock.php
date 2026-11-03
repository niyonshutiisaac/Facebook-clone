<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserBlock extends Model
{
    /** @use HasFactory<\Database\Factories\UserBlockFactory> */
    use HasFactory;

    protected $table = 'user_blocks';

    /**
     * @var list<string>
     */
    protected $fillable = ['user_id', 'blocked_user_id', 'reason'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function blockedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_user_id');
    }
}
