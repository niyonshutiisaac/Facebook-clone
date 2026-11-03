<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSetting extends Model
{
    /** @use HasFactory<\Database\Factories\UserSettingFactory> */
    use HasFactory;

    protected $table = 'user_settings';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id', 'theme', 'language', 'sound_enabled', 'email_digest_enabled',
        'push_enabled', 'show_active_status', 'tag_review_enabled',
        'profile_visitors_enabled', 'notification_preferences', 'feed_preferences',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sound_enabled' => 'boolean',
            'email_digest_enabled' => 'boolean',
            'push_enabled' => 'boolean',
            'show_active_status' => 'boolean',
            'tag_review_enabled' => 'boolean',
            'profile_visitors_enabled' => 'boolean',
            'notification_preferences' => 'array',
            'feed_preferences' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
