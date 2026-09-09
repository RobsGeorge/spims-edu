<?php

namespace App\Http\Controllers;

use App\Enums\Currency;
use App\Enums\OfferingMode;
use App\Enums\OfferingStatus;
use App\Enums\ProgramType;
use App\Enums\RequirementType;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Program;
use App\Services\Academics\CourseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q'    => 'nullable|string|max:100',
            'tab'  => 'nullable|in:programs,courses,standalone',
            'type' => 'nullable|in:all,diploma,certificate,degree',
            'mode' => 'nullable|in:all,cohort,self_paced',
            'price'    => 'nullable|in:all,free,paid',
            'interest' => 'nullable|in:all,flagged',
            'sort'     => 'nullable|in:code,interest',
        ]);

        $tab  = $filters['tab']  ?? 'programs';
        $type = strtolower($filters['type'] ?? 'all');
        $mode = $filters['mode'] ?? 'all';

        // Map program type filter
        $programTypeEnum = match ($type) {
            'diploma'     => ProgramType::Diploma,
            'certificate' => ProgramType::Certificate,
            'degree'      => ProgramType::Degree,
            default       => null,
        };

        // Map delivery mode filter
        $modeEnum = match ($mode) {
            'cohort'     => OfferingMode::Cohort,
            'self_paced' => OfferingMode::SelfPaced,
            default      => null,
        };

        // Pre-compute course IDs with the requested mode (shared across tabs)
        $courseIdsWithMode = null;
        if ($modeEnum !== null) {
            $courseIdsWithMode = CourseOffering::query()
                ->where('mode', $modeEnum->value)
                ->pluck('course_id')
                ->all();
        }

        // ── Programs tab ──────────────────────────────────────────────────────
        $programsQuery = Program::query()
            ->where('active', true)
            ->with([
                'programCourses.course',
                'applicationForms' => fn ($q) => $q->where('active', true),
            ])
            ->orderBy('name');

        if ($programTypeEnum !== null) {
            $programsQuery->where('type', $programTypeEnum->value);
        }

        if ($courseIdsWithMode !== null) {
            $programsQuery->whereHas(
                'programCourses',
                fn ($q) => $q->whereIn('course_id', $courseIdsWithMode)
            );
        }

        $programs = $programsQuery->get()
            ->filter(fn ($p) => $p->programCourses->isNotEmpty())
            ->values();

        // Annotate each program with its total required-course USD price
        foreach ($programs as $program) {
            $program->total_price_usd = (int) $program->programCourses
                ->filter(fn ($pc) => $pc->requirement === RequirementType::Required && $pc->course)
                ->sum(fn ($pc) => $pc->course->is_free ? 0 : (int) ($pc->course->default_price_usd ?? 0));
        }

        // ── Courses tab (all active courses, filterable) ──────────────────────
        $coursesQuery = Course::query()
            ->where('active', true)
            ->withCount('interestFlags')
            ->with([
                'programCourses.program.applicationForms' => fn ($q) => $q->where('active', true),
                'prerequisites',
            ]);

        if (! empty($filters['q'])) {
            $term = '%'.$filters['q'].'%';
            $coursesQuery->where(function ($q) use ($term) {
                $q->where('code', 'like', $term)->orWhere('title', 'like', $term);
            });
        }

        // Program type filter: show courses belonging to programs of that type
        if ($programTypeEnum !== null) {
            $coursesQuery->whereHas(
                'programCourses',
                fn ($q) => $q->whereHas(
                    'program',
                    fn ($q2) => $q2->where('type', $programTypeEnum->value)
                )
            );
        }

        // Mode filter: courses with at least one offering in the requested mode
        if ($courseIdsWithMode !== null) {
            $coursesQuery->whereIn('id', $courseIdsWithMode);
        }

        $price = $filters['price'] ?? 'all';
        if ($price === 'free') {
            $coursesQuery->where('is_free', true);
        } elseif ($price === 'paid') {
            $coursesQuery->where('is_free', false);
        }

        $interest = $filters['interest'] ?? 'all';
        if ($interest === 'flagged' && $request->user()) {
            $coursesQuery->whereHas(
                'interestFlags',
                fn ($q) => $q->where('student_id', $request->user()->id)
            );
        }

        $sort = $filters['sort'] ?? 'code';
        if ($sort === 'interest') {
            $coursesQuery->orderByDesc('interest_flags_count')->orderBy('code');
        } else {
            $coursesQuery->orderBy('code');
        }

        $courses = $coursesQuery->paginate(12)->appends(
            $request->except(['fragment', 'skeleton', 'page'])
        );

        // ── Standalone tab (is_standalone = true) ────────────────────────────
        $standaloneQuery = Course::query()
            ->where('active', true)
            ->where('is_standalone', true)
            ->withCount('interestFlags')
            ->with(['programCourses.program', 'prerequisites']);

        if (! empty($filters['q'])) {
            $term = '%'.$filters['q'].'%';
            $standaloneQuery->where(function ($q) use ($term) {
                $q->where('code', 'like', $term)->orWhere('title', 'like', $term);
            });
        }

        if ($courseIdsWithMode !== null) {
            $standaloneQuery->whereIn('id', $courseIdsWithMode);
        }

        if ($price === 'free') {
            $standaloneQuery->where('is_free', true);
        } elseif ($price === 'paid') {
            $standaloneQuery->where('is_free', false);
        }

        if ($interest === 'flagged' && $request->user()) {
            $standaloneQuery->whereHas(
                'interestFlags',
                fn ($q) => $q->where('student_id', $request->user()->id)
            );
        }

        $standaloneQuery->orderBy('code');
        $standaloneCourses = $standaloneQuery->get();

        // ── Offerings keyed by course_id (for Courses tab cards) ─────────────
        $courseIds = $courses->getCollection()->pluck('id')->all();
        $offeringsByCourse = CourseOffering::query()
            ->whereIn('course_id', $courseIds)
            ->whereIn('status', [OfferingStatus::Open->value, OfferingStatus::InProgress->value])
            ->with('course')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('course_id');

        // Offerings keyed for standalone courses
        $standaloneIds = $standaloneCourses->pluck('id')->all();
        $standaloneOfferingsByCourse = CourseOffering::query()
            ->whereIn('course_id', $standaloneIds)
            ->whereIn('status', [OfferingStatus::Open->value, OfferingStatus::InProgress->value])
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('course_id');

        $hasActiveFilters = ($filters['q'] ?? '') !== ''
            || $type !== 'all'
            || $mode !== 'all'
            || $price !== 'all'
            || $interest === 'flagged'
            || $sort === 'interest';

        $payload = [
            'tab'               => $tab,
            'programs'          => $programs,
            'courses'           => $courses,
            'standaloneCourses' => $standaloneCourses,
            'offeringsByCourse' => $offeringsByCourse,
            'standaloneOfferingsByCourse' => $standaloneOfferingsByCourse,
            'showSkeletons'     => $request->boolean('skeleton'),
            'hasActiveFilters'  => $hasActiveFilters,
            'filters'           => [
                'q'        => $filters['q'] ?? '',
                'tab'      => $tab,
                'type'     => $type,
                'mode'     => $mode,
                'price'    => $price,
                'interest' => $interest,
                'sort'     => $sort,
            ],
        ];

        if ($request->boolean('fragment')) {
            return view('catalog.partials.results', $payload);
        }

        return view('catalog.index', $payload);
    }

    public function flagInterest(Request $request, Course $course, CourseService $service): RedirectResponse
    {
        $service->flagInterest($request->user(), $course);

        return back()->with('status', __('academics.interest_flagged'));
    }
}
