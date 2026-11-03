<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ContentReport;
use App\Models\ReportReason;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ReportController extends Controller
{
    /**
     * File a report against any reportable entity.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reportable_type' => ['required', 'string',
                'in:post,comment,message,user,group,page,photo,story,reel'],
            'reportable_id' => ['required', 'uuid'],
            'reason_id' => ['nullable', 'uuid', 'exists:report_reasons,id'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        if ($validated['reason_id'] !== null) {
            $reason = ReportReason::query()->find($validated['reason_id']);

            if ($reason !== null && $reason->requires_description && blank($validated['description'] ?? null)) {
                return response()->json([
                    'message' => 'Please describe the problem so we can review it.',
                    'errors' => ['description' => ['A description is required for this reason.']],
                ], 422);
            }
        }

        $report = ContentReport::query()->create([
            'reporter_id' => $user->id,
            ...$validated,
        ]);

        $user->logActivity('report.created', $validated['reportable_type'], $validated['reportable_id']);

        return response()->json([
            'report' => $report,
            'message' => 'Thanks — we will review this and let you know the outcome.',
        ], 201);
    }

    public function reasons(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['nullable', 'string', 'in:post,comment,message,user,group,page,photo,story,reel'],
        ]);

        $query = ReportReason::query()->orderBy('position');

        if (isset($validated['type'])) {
            $query->where('applies_to', 'like', '%'.$validated['type'].'%');
        }

        return response()->json(['data' => $query->get()]);
    }
}
