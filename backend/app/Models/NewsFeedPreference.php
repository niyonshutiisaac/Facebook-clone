<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FeedPreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsFeedPreference extends Model
{
    /** @use HasFactory<\Database\Factories\NewsFeedPreferenceFactory> */
    use HasFactory;

    protected $table = 'newsfeed_preferences';

    /**
     * @var list<string>
     */
    protected $fillable = ['user_id', 'subject_type', 'subject_id', 'preference', 'snoozed_until'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['snoozed_until' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function preferenceEnum(): FeedPreference
    {
        return FeedPreference::tryFrom((string) $this->preference) ?? FeedPreference::Default;
    }

    public function isActive(): bool
    {
        return $this->preferenceEnum() !== FeedPreference::Snooze
            || ($this->snoozed_until !== null && $this->snoozed_until->isFuture());
    }
}
