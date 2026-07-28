<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Services\Academics\CourseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function index(): View
    {
        return view('catalog.index', [
            'courses' => Course::query()
                ->where('active', true)
                ->withCount('interestFlags')
                ->orderBy('code')
                ->paginate(20),
        ]);
    }

    public function flagInterest(Request $request, Course $course, CourseService $service): RedirectResponse
    {
        $service->flagInterest($request->user(), $course);

        return back()->with('status', __('academics.interest_flagged'));
    }
}
