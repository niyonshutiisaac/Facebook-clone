<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSession extends Model
{
    /** @use HasFactory<\Database\Factories\UserSessionFactory> */
    use HasFactory;

    protected $table = 'user_sessions';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id', 'session_id', 'ip_address', 'user_agent', 'device_name',
        'device_type', 'platform', 'browser', 'city', 'region', 'country',
        'is_current', 'last_activity_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
            'last_activity_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
