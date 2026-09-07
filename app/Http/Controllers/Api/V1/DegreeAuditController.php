<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StudentProgram;
use App\Services\Enrollment\DegreeAuditService;
use App\Support\Api\StudentRecordGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DegreeAuditController extends Controller
{
    public function show(
        Request $request,
        StudentProgram $studentProgram,
        DegreeAuditService $audit,
        StudentRecordGuard $guard,
    ): JsonResponse {
        $guard->ownRead($request->user(), $studentProgram->student_id);

        return response()->json([
            'data' => $audit->audit($request->user(), $studentProgram),
        ]);
    }

    /**
     * Compute-only what-if. Does not persist academic_records or fulfillments.
     */
    public function whatIf(
        Request $request,
        StudentProgram $studentProgram,
        DegreeAuditService $audit,
        StudentRecordGuard $guard,
    ): JsonResponse {
        $guard->ownRead($request->user(), $studentProgram->student_id);

        $data = $request->validate([
            'hypothetical_course_ids' => ['array'],
            'hypothetical_course_ids.*' => ['exists:courses,id'],
        ]);

        $courseIds = array_values(array_map(
            static fn ($id) => (string) $id,
            $data['hypothetical_course_ids'] ?? [],
        ));

        return response()->json([
            'data' => $audit->whatIf($studentProgram, $courseIds),
        ]);
    }
}
