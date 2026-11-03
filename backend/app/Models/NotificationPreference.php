<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    /** @use HasFactory<\Database\Factories\NotificationPreferenceFactory> */
    use HasFactory;

    protected $table = 'notification_preferences';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id', 'type', 'in_app_enabled', 'push_enabled',
        'email_enabled', 'email_frequency', 'quiet_hours_start', 'quiet_hours_end',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'in_app_enabled' => 'boolean',
            'push_enabled' => 'boolean',
            'email_enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Quiet hours wrap around midnight, so the comparison has to handle the
     * case where start is later than end (e.g. 22:00 → 07:00).
     */
    public function isQuietHour(\DateTimeInterface $at = null): bool
    {
        if ($this->quiet_hours_start === null || $this->quiet_hours_end === null) {
            return false;
        }

        $now = ($at ?? now())->format('H:i');
        $start = $this->quiet_hours_start;
        $end = $this->quiet_hours_end;

        return $start <= $end
            ? ($now >= $start && $now <= $end)
            : ($now >= $start || $now <= $end);
    }
}
