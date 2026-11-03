<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ContentReport extends Model
{
    /** @use HasFactory<\Database\Factories\ContentReportFactory> */
    use HasFactory;

    protected $table = 'content_reports';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'reporter_id', 'reviewed_by', 'reportable_type', 'reportable_id',
        'reason_id', 'description', 'status', 'resolution', 'reviewed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(ReportReason::class);
    }

    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
