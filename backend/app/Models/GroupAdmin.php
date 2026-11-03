<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupAdmin extends Model
{
    /** @use HasFactory<\Database\Factories\GroupAdminFactory> */
    use HasFactory;

    protected $table = 'group_admins';

    /**
     * @var list<string>
     */
    protected $fillable = ['group_id', 'user_id', 'role', 'permissions', 'appointed_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'appointed_at' => 'datetime',
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
}
