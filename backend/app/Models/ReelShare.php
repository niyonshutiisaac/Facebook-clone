<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReelShare extends Model
{
    /** @use HasFactory<\Database\Factories\ReelShareFactory> */
    use HasFactory;

    protected $table = 'reel_shares';

    /**
     * @var list<string>
     */
    protected $fillable = ['reel_id', 'user_id', 'destination'];

    public function reel(): BelongsTo
    {
        return $this->belongsTo(Reel::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
