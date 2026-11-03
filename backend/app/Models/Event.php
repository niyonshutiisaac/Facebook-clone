<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Event extends Model
{
    /** @use HasFactory<\Database\Factories\EventFactory> */
    use HasFactory;
    use Searchable;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id', 'group_id', 'name', 'description', 'privacy',
        'starts_at', 'ends_at', 'timezone', 'location_name', 'address',
        'latitude', 'longitude', 'is_online', 'online_url', 'cover_path',
        'going_count', 'interested_count', 'requires_approval',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_online' => 'boolean',
            'going_count' => 'integer',
            'interested_count' => 'integer',
            'requires_approval' => 'boolean',
        ];
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(EventAttendee::class);
    }

    public function invites(): HasMany
    {
        return $this->hasMany(EventInvite::class);
    }

    public function isPast(): bool
    {
        return $this->starts_at !== null && $this->starts_at->isPast();
    }

    public function responseOf(User $user): ?string
    {
        return $this->attendees()->where('user_id', $user->id)->value('status');
    }

    /**
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('starts_at', '>=', now())->orderBy('starts_at');
    }

    /**
     * Events within `radiusKm` of a coordinate, using the haversine formula.
     *
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    public function scopeNearby(Builder $query, float $latitude, float $longitude, int $radiusKm = 50): Builder
    {
        return $query->selectRaw(
            '*, (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance',
            [$latitude, $longitude, $latitude]
        )->having('distance', '<=', $radiusKm)->orderBy('distance');
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'location_name' => $this->location_name,
            'starts_at' => $this->starts_at?->timestamp,
        ];
    }
}
