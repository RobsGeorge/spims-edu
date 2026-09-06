<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EnrollmentStatus;
use App\Enums\GradeStatus;
use App\Exceptions\AuthorizationException;
use App\Http\Controllers\Controller;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\GradebookComponent;
use App\Models\User;
use App\Services\Gradebook\GradebookService;
use App\Support\Api\ConfirmationToken;
use App\Support\AuthorizeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeachGradebookController extends Controller
{
    public function __construct(
        private readonly GradebookService $gradebook,
        private readonly AuthorizeService $authorize,
        private readonly ConfirmationToken $confirmation,
    ) {}

    public function show(Request $request, CourseOffering $offering): JsonResponse
    {
        $actor = $request->user();
        $this->authorize->authorize($actor, 'assignments.grade', $offering);

        $components = GradebookComponent::query()
            ->where('offering_id', $offering->id)
            ->orderBy('name')
            ->get();

        $students = Enrollment::query()
            ->where('offering_id', $offering->id)
            ->where('status', EnrollmentStatus::Enrolled)
            ->with('student')
            ->orderBy('enrolled_at')
            ->get()
            ->map(function (Enrollment $enrollment) {
                $computed = $this->gradebook->computeEnrollment($enrollment);

                return [
                    'student_id' => $enrollment->student_id,
                    'first_name' => $enrollment->student?->first_name,
                    'last_name' => $enrollment->student?->last_name,
                    'grade_status' => $enrollment->grade_status?->value,
                    'final_percent' => $enrollment->final_percent,
                    'final_letter' => $enrollment->final_letter,
                    'percent' => $computed['percent'],
                    'components' => $computed['components'],
                ];
            })
            ->values();

        $payload = [
            'offering_id' => $offering->id,
            'locked' => Enrollment::query()
                ->where('offering_id', $offering->id)
                ->where('grade_status', GradeStatus::Locked)
                ->exists(),
            'components' => $components->map(fn (GradebookComponent $component) => [
                'id' => $component->id,
                'name' => $component->name,
                'weight_percent' => $component->weight_percent,
                'kind' => $component->kind->value,
            ])->values(),
            'students' => $students,
        ];

        $lockConfirmation = $this->lockConfirmation($actor, $offering);
        if ($lockConfirmation !== null) {
            $payload['confirmation'] = $lockConfirmation;
        }

        return response()->json(['data' => $payload]);
    }

    public function submit(Request $request, CourseOffering $offering): JsonResponse
    {
        $this->gradebook->submitGrades($request->user(), $offering);

        return response()->json(['data' => ['submitted' => true]]);
    }

    public function lock(Request $request, CourseOffering $offering): JsonResponse
    {
        $actor = $request->user();
        $this->authorize->authorize($actor, 'gradebook.lock', $offering);

        $data = $request->validate([
            'confirmation' => 'nullable|string',
        ]);

        $this->confirmation->consume(
            $actor,
            'gradebook.lock',
            $offering->id,
            $data['confirmation'] ?? null,
        );

        $this->gradebook->lockGrades($actor, $offering);

        return response()->json(['data' => ['locked' => true]]);
    }

    /** @return array{confirmation_token: string, consequences: array<int, mixed>, expires_at: string}|null */
    private function lockConfirmation(User $actor, CourseOffering $offering): ?array
    {
        try {
            $this->authorize->authorize($actor, 'gradebook.lock', $offering);
        } catch (AuthorizationException) {
            return null;
        }

        return $this->confirmation->issue(
            $actor,
            'gradebook.lock',
            $offering->id,
            [
                __('teach.lock_confirm_title'),
                __('teach.lock_confirm_body'),
            ],
        );
    }
}
