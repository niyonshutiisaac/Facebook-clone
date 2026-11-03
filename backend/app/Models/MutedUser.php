<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MutedUser extends Model
{
    /** @use HasFactory<\Database\Factories\MutedUserFactory> */
    use HasFactory;

    protected $table = 'muted_users';

    /**
     * @var list<string>
     */
    protected $fillable = ['user_id', 'muted_id', 'muted_until', 'reason'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['muted_until' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function muted(): BelongsTo
    {
        return $this->belongsTo(User::class, 'muted_id');
    }

    /**
     * Snoozes expire on their own, so every read filters out rows whose
     * `muted_until` is in the past. A null value means "muted forever".
     *
     * @param  Builder<MutedUser>  $query
     * @return Builder<MutedUser>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(
            fn (Builder $q) => $q->whereNull('muted_until')->orWhere('muted_until', '>', now())
        );
    }
}
