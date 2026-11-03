<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventInvite extends Model
{
    /** @use HasFactory<\Database\Factories\EventInviteFactory> */
    use HasFactory;

    protected $table = 'event_invites';

    /**
     * @var list<string>
     */
    protected $fillable = ['event_id', 'inviter_id', 'invitee_id', 'status', 'responded_at'];

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

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }

    public function invitee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invitee_id');
    }
}
