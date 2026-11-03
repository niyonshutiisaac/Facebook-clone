<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Request;

/**
 * Records a row in `activity_log` for security and privacy relevant actions.
 */
trait TracksActivity
{
    /**
     * @param  array<string, mixed>  $meta
     */
    protected function logActivity(string $action, ?string $subjectType = null, ?string $subjectId = null, array $meta = []): ActivityLog
    {
        return ActivityLog::create([
            'user_id' => $this->getKey(),
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'meta' => $meta !== [] ? $meta : null,
            'ip_address' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 512) ?: null,
        ]);
    }
}
