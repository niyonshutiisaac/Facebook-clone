<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventAttendee extends Model
{
    /** @use HasFactory<\Database\Factories\EventAttendeeFactory> */
    use HasFactory;

    protected $table = 'event_attendees';

    /**
     * @var list<string>
     */
    protected $fillable = ['event_id', 'user_id', 'status', 'responded_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['responded_at' => 'datetime'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isGoing(): bool
    {
        return $this->status === 'going';
    }
}
