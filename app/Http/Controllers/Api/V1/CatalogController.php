<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OfferingStatus;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Program;
use App\Services\Academics\CourseService;
use App\Support\Api\PaginatedEnvelope;
use App\Support\MoneyPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:100',
            'type' => 'nullable|in:all,standalone,program',
            'price' => 'nullable|in:all,free,paid',
        ]);

        $query = Course::query()
            ->where('active', true)
            ->withCount('interestFlags')
            ->with(['prerequisites', 'programCourses.program'])
            ->orderBy('code');

        if (! empty($filters['q'])) {
            $term = '%'.$filters['q'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('code', 'like', $term)->orWhere('title', 'like', $term);
            });
        }

        $type = $filters['type'] ?? 'all';
        if ($type === 'standalone') {
            $query->where('is_standalone', true);
        } elseif ($type === 'program') {
            $query->where('is_standalone', false);
        }

        $price = $filters['price'] ?? 'all';
        if ($price === 'free') {
            $query->where('is_free', true);
        } elseif ($price === 'paid') {
            $query->where('is_free', false);
        }

        $perPage = PaginatedEnvelope::perPage($request->integer('per_page') ?: null);
        $page = $query->paginate($perPage);
        $country = $request->user()?->country_code ?? $request->query('country');

        $offerings = CourseOffering::query()
            ->whereIn('course_id', $page->getCollection()->pluck('id')->all())
            ->whereIn('status', [OfferingStatus::Open, OfferingStatus::InProgress])
            ->with('course')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('course_id');

        $page->setCollection($page->getCollection()->map(
            fn (Course $course) => $this->coursePayload(
                $course,
                $offerings->get($course->id, collect()),
                is_string($country) ? $country : null,
            )
        ));

        $envelope = PaginatedEnvelope::from($page);
        $envelope['programs'] = Program::query()
            ->where('active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type'])
            ->map(fn (Program $program) => [
                'id' => $program->id,
                'code' => $program->code,
                'name' => $program->name,
                'type' => $program->type->value,
            ])
            ->values();

        return response()->json($envelope);
    }

    public function showCourse(Request $request, Course $course): JsonResponse
    {
        if (! $course->active) {
            abort(404);
        }

        $course->load(['prerequisites', 'programCourses.program']);
        $country = $request->user()?->country_code ?? $request->query('country');
        $offerings = CourseOffering::query()
            ->where('course_id', $course->id)
            ->whereIn('status', [OfferingStatus::Open, OfferingStatus::InProgress])
            ->with('course')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => $this->coursePayload($course, $offerings, is_string($country) ? $country : null),
        ]);
    }

    public function flagInterest(Request $request, Course $course, CourseService $service): JsonResponse
    {
        $flag = $service->flagInterest($request->user(), $course);

        return response()->json([
            'data' => [
                'id' => $flag->id,
                'course_id' => $flag->course_id,
                'student_id' => $flag->student_id,
            ],
        ], 201);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, CourseOffering>  $offerings
     * @return array<string, mixed>
     */
    private function coursePayload(Course $course, $offerings, ?string $country): array
    {
        return [
            'id' => $course->id,
            'code' => $course->code,
            'title' => $course->title,
            'cover_image_url' => $course->coverUrl(),
            'credit_hours' => $course->credit_hours,
            'is_free' => $course->is_free,
            'is_standalone' => $course->is_standalone,
            'prerequisites' => $course->prerequisites->map(fn (Course $prereq) => [
                'id' => $prereq->id,
                'code' => $prereq->code,
                'title' => $prereq->title,
            ])->values(),
            'offerings' => $offerings->map(function (CourseOffering $offering) use ($country) {
                $price = $offering->resolvedPriceForCountry($country);

                return [
                    'id' => $offering->id,
                    'mode' => $offering->mode->value,
                    'status' => $offering->status->value,
                    'price' => MoneyPayload::fromMinor((int) $price['amount_minor'], $price['currency']),
                ];
            })->values(),
        ];
    }
}
