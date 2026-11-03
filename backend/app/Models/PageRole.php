<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageRole extends Model
{
    /** @use HasFactory<\Database\Factories\PageRoleFactory> */
    use HasFactory;

    protected $table = 'page_roles';

    /**
     * @var list<string>
     */
    protected $fillable = ['page_id', 'user_id', 'role', 'permissions', 'granted_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'granted_at' => 'datetime',
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

    public function canManage(): bool
    {
        return in_array($this->role, ['admin', 'editor'], true);
    }
}
