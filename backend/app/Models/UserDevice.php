<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDevice extends Model
{
    /** @use HasFactory<\Database\Factories\UserDeviceFactory> */
    use HasFactory;

    protected $table = 'user_devices';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id', 'fingerprint', 'device_name', 'platform', 'is_trusted',
        'last_used_at', 'push_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_trusted' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
