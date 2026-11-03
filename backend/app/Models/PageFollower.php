<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageFollower extends Model
{
    /** @use HasFactory<\Database\Factories\PageFollowerFactory> */
    use HasFactory;

    protected $table = 'page_followers';

    /**
     * @var list<string>
     */
    protected $fillable = ['page_id', 'user_id', 'receives_notifications', 'followed_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'receives_notifications' => 'boolean',
            'followed_at' => 'datetime',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
