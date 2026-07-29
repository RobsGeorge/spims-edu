<?php

namespace App\Http\Controllers;

use App\Enums\OfferingStatus;
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
            'q' => 'nullable|string|max:100',
            'type' => 'nullable|in:all,standalone,program',
            'price' => 'nullable|in:all,free,paid',
        ]);

        $query = Course::query()
            ->where('active', true)
            ->withCount('interestFlags')
            ->with(['programCourses.program.applicationForms' => fn ($q) => $q->where('active', true)])
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

        $courses = $query->paginate(12)->withQueryString();

        $courseIds = $courses->getCollection()->pluck('id')->all();
        $offerings = CourseOffering::query()
            ->whereIn('course_id', $courseIds)
            ->whereIn('status', [OfferingStatus::Open, OfferingStatus::InProgress])
            ->with('course')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('course_id');

        return view('catalog.index', [
            'courses' => $courses,
            'offeringsByCourse' => $offerings,
            'filters' => [
                'q' => $filters['q'] ?? '',
                'type' => $type,
                'price' => $price,
            ],
            'programs' => Program::query()->where('active', true)->orderBy('code')->get(),
        ]);
    }

    public function flagInterest(Request $request, Course $course, CourseService $service): RedirectResponse
    {
        $service->flagInterest($request->user(), $course);

        return back()->with('status', __('academics.interest_flagged'));
    }
}
