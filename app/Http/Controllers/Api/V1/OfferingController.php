<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Models\ContentItem;
use App\Models\CourseOffering;
use App\Models\Week;
use App\Services\Learning\CoursePlayerService;
use App\Services\Learning\StudentGradesService;
use App\Services\Offerings\LearningAccessService;
use App\Services\Offerings\LearningProgressService;
use App\Services\Storage\ObjectStorageService;
use App\Support\Api\PaginatedEnvelope;
use App\Support\Api\StudentPayload;
use App\Support\Api\StudentRecordGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class OfferingController extends Controller
{
    public function __construct(
        private readonly StudentRecordGuard $guard,
        private readonly CoursePlayerService $player,
        private readonly LearningProgressService $progress,
        private readonly LearningAccessService $learning,
        private readonly StudentGradesService $grades,
        private readonly ObjectStorageService $storage,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = PaginatedEnvelope::perPage($request->integer('per_page') ?: null);

        $page = \App\Models\Enrollment::query()
            ->where('student_id', $user->id)
            ->whereIn('status', array_map(fn (EnrollmentStatus $status) => $status->value, $this->learning->allowedStatuses()))
            ->with(['offering.course', 'offering.semester'])
            ->latest('enrolled_at')
            ->paginate($perPage);

        $page->setCollection($page->getCollection()->map(function ($enrollment) {
            $offering = $enrollment->offering;

            return [
                'id' => $offering->id,
                'enrollment_id' => $enrollment->id,
                'course_code' => $offering->course?->code,
                'course_title' => $offering->course?->title,
                'mode' => $offering->mode->value,
                'status' => $offering->status->value,
                'progress_percent' => $enrollment->progress_percent,
                'semester' => $offering->semester?->name,
            ];
        }));

        return response()->json(PaginatedEnvelope::from($page));
    }

    public function show(Request $request, CourseOffering $offering): JsonResponse
    {
        $this->guard->enrollmentForRead($request->user(), $offering);
        $payload = $this->player->playerPayload($request->user(), $offering);
        $offering = $payload['offering']->loadMissing(['course', 'semester', 'staff.user']);

        return response()->json([
            'data' => [
                'id' => $offering->id,
                'course_code' => $offering->course?->code,
                'course_title' => $offering->course?->title,
                'mode' => $offering->mode->value,
                'status' => $offering->status->value,
                'progress_percent' => $payload['progress'],
                'semester' => $offering->semester === null ? null : [
                    'id' => $offering->semester->id,
                    'name' => $offering->semester->name,
                    'start_date' => StudentPayload::iso($offering->semester->start_date),
                    'end_date' => StudentPayload::iso($offering->semester->end_date),
                ],
                'staff' => $offering->staff->map(fn ($row) => [
                    'id' => $row->user_id,
                    'role' => $row->role->value,
                    'first_name' => $row->user?->first_name,
                    'last_name' => $row->user?->last_name,
                ])->values(),
                'gating' => [
                    'completed_weeks' => $payload['completed'],
                    'total_weeks' => count($payload['weeks']),
                    'unlocked_week_ids' => collect($payload['weeks'])
                        ->filter(fn ($week) => $week['unlocked'])
                        ->pluck('id')
                        ->values()
                        ->all(),
                ],
            ],
        ]);
    }

    public function weeks(Request $request, CourseOffering $offering): JsonResponse
    {
        $this->guard->enrollmentForRead($request->user(), $offering);
        $payload = $this->player->playerPayload($request->user(), $offering);
        $perPage = PaginatedEnvelope::perPage($request->integer('per_page') ?: null);
        $pageNum = max(1, $request->integer('page') ?: 1);

        $weeks = collect($payload['weeks'])->map(fn (array $week) => [
            'id' => $week['id'],
            'number' => $week['number'],
            'title' => $week['title'],
            'unlocked' => $week['unlocked'],
            'completed' => $week['completed'],
        ]);

        $paginator = new LengthAwarePaginator(
            $weeks->forPage($pageNum, $perPage)->values(),
            $weeks->count(),
            $perPage,
            $pageNum,
        );

        return response()->json(PaginatedEnvelope::from($paginator));
    }

    public function weekItems(Request $request, CourseOffering $offering, Week $week): JsonResponse
    {
        $enrollment = $this->guard->enrollmentForRead($request->user(), $offering);
        $this->learning->assertWeekBelongsToOffering($week, $offering);
        $payload = $this->player->playerPayload($request->user(), $offering);
        $weekRow = collect($payload['weeks'])->firstWhere('id', $week->id);
        $unlocked = (bool) ($weekRow['unlocked'] ?? false);
        $completedIds = $this->progress->completedItemIds($enrollment);

        $week->load('items');
        $items = $week->items->filter(fn (ContentItem $item) => $item->isPublished())->sortBy('order')->values()->map(
            fn (ContentItem $item) => StudentPayload::itemMeta(
                $item,
                $unlocked,
                in_array($item->id, $completedIds, true),
            )
        );

        $perPage = PaginatedEnvelope::perPage($request->integer('per_page') ?: null);
        $pageNum = max(1, $request->integer('page') ?: 1);
        $paginator = new LengthAwarePaginator(
            $items->forPage($pageNum, $perPage)->values(),
            $items->count(),
            $perPage,
            $pageNum,
        );

        return response()->json(PaginatedEnvelope::from($paginator));
    }

    public function completeWeek(Request $request, CourseOffering $offering, Week $week): JsonResponse
    {
        $this->guard->enrollmentForWrite($request->user(), $offering);
        $this->learning->assertWeekBelongsToOffering($week, $offering);
        $enrollment = $this->player->completeWeek($request->user(), $offering, $week);

        return response()->json([
            'data' => [
                'enrollment_id' => $enrollment->id,
                'week_id' => $week->id,
                'progress_percent' => $enrollment->progress_percent,
                'completed' => true,
            ],
        ]);
    }

    public function grades(Request $request, CourseOffering $offering): JsonResponse
    {
        $this->guard->enrollmentForRead($request->user(), $offering);
        $row = $this->grades->forOffering($request->user(), $offering) ?? [
            'running_percent' => null,
            'final_letter' => null,
            'final_percent' => null,
            'grade_status' => null,
            'items' => [],
        ];

        $items = collect($row['items'] ?? [])->map(fn (array $item) => [
            'kind' => $item['kind'],
            'title' => $item['title'],
            'score' => $item['score'],
            'status' => $item['status'],
        ])->values();

        return response()->json([
            'data' => [
                'course_code' => $row['course_code'] ?? $offering->course?->code,
                'course_title' => $row['course_title'] ?? $offering->course?->title,
                'running_percent' => $row['running_percent'] ?? null,
                'final_letter' => $row['final_letter'] ?? null,
                'final_percent' => $row['final_percent'] ?? null,
                'grade_status' => $row['grade_status'] ?? null,
                'items' => $items,
            ],
        ]);
    }
}
