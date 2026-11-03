<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Audience;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPrivacySetting extends Model
{
    /** @use HasFactory<\Database\Factories\UserPrivacySettingFactory> */
    use HasFactory;

    protected $table = 'user_privacy_settings';

    /**
     * @var list<string>
     */
    protected $fillable = ['user_id', 'field', 'audience', 'excluded_user_ids', 'allowed_user_ids'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'excluded_user_ids' => 'array',
            'allowed_user_ids' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function audienceEnum(): Audience
    {
        return Audience::tryFrom((string) $this->audience) ?? Audience::Public;
    }
}
