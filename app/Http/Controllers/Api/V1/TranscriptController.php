<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Credentials\CredentialService;
use App\Support\Api\StudentPayload;
use App\Support\AuthorizeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TranscriptController extends Controller
{
    public function show(Request $request, AuthorizeService $authorize, CredentialService $credentials): JsonResponse
    {
        $authorize->authorize($request->user(), 'transcript.view');
        $data = $credentials->transcriptData($request->user());

        return response()->json([
            'data' => [
                'gpa' => $data['gpa'],
                'records' => $data['records']->map(fn ($record) => [
                    'id' => $record->id,
                    'course_code' => $record->course?->code,
                    'course_title' => $record->course?->title,
                    'letter_grade' => $record->letter_grade,
                    'percent' => $record->percent,
                    'gpa_points' => $record->gpa_points,
                    'credit_hours' => $record->credit_hours,
                    'term' => $record->term,
                    'is_passing' => $record->is_passing,
                    'completed_at' => StudentPayload::iso($record->completed_at),
                ])->values(),
            ],
        ]);
    }
}
