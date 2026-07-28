<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContentItemType;
use App\Enums\OfferingMode;
use App\Enums\OfferingStaffRole;
use App\Enums\OfferingStatus;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Semester;
use App\Models\User;
use App\Models\Week;
use App\Services\Offerings\OfferingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OfferingController extends Controller
{
    public function index(): View
    {
        return view('admin.offerings.index', [
            'offerings' => CourseOffering::query()->with(['course', 'semester'])->latest()->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.offerings.create', [
            'courses' => Course::query()->where('active', true)->orderBy('code')->get(),
            'semesters' => Semester::query()->orderByDesc('start_date')->get(),
            'modes' => OfferingMode::cases(),
        ]);
    }

    public function store(Request $request, OfferingService $service): RedirectResponse
    {
        $data = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'semester_id' => 'nullable|exists:semesters,id',
            'mode' => 'required|in:'.implode(',', array_column(OfferingMode::cases(), 'value')),
            'seat_capacity' => 'nullable|integer|min:1',
            'attendance_threshold_percent' => 'nullable|numeric|min:0|max:100',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'status' => 'nullable|in:'.implode(',', array_column(OfferingStatus::cases(), 'value')),
            'clone' => 'boolean',
        ]);

        $course = Course::query()->findOrFail($data['course_id']);
        $offering = ! empty($data['clone'])
            ? $service->cloneFromCourse($request->user(), $course, $data)
            : $service->create($request->user(), $data);

        return redirect()->route('admin.offerings.show', $offering)->with('status', __('offerings.offering_created'));
    }

    public function show(CourseOffering $offering): View
    {
        $offering->load(['course', 'semester', 'staff.user', 'weeks.items']);

        return view('admin.offerings.show', [
            'offering' => $offering,
            'staffRoles' => OfferingStaffRole::cases(),
            'instructors' => User::query()->whereHas('roles', fn ($q) => $q->whereIn('role', ['INSTRUCTOR', 'TA', 'ACADEMIC_ADMIN']))->orderBy('first_name')->get(),
            'contentTypes' => ContentItemType::cases(),
        ]);
    }

    public function assignStaff(Request $request, CourseOffering $offering, OfferingService $service): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|in:'.implode(',', array_column(OfferingStaffRole::cases(), 'value')),
        ]);

        $service->assignStaff($request->user(), $offering, $data['user_id'], $data['role']);

        return back()->with('status', __('offerings.staff_assigned'));
    }

    public function setPricing(Request $request, CourseOffering $offering, OfferingService $service): RedirectResponse
    {
        $data = $request->validate([
            'price_usd_override' => 'nullable|integer|min:0',
            'price_egp_override' => 'nullable|integer|min:0',
        ]);

        $service->setPricing(
            $request->user(),
            $offering,
            $data['price_usd_override'] ?? null,
            $data['price_egp_override'] ?? null
        );

        return back()->with('status', __('offerings.pricing_saved'));
    }

    public function addWeek(Request $request, CourseOffering $offering, OfferingService $service): RedirectResponse
    {
        $data = $request->validate([
            'number' => 'required|integer|min:1',
            'title' => 'required|string|max:255',
            'unlock_date' => 'nullable|date',
        ]);

        $service->addWeek($request->user(), $offering, $data);

        return back()->with('status', __('offerings.week_added'));
    }

    public function addContent(Request $request, Week $week, OfferingService $service): RedirectResponse
    {
        $data = $request->validate([
            'type' => 'required|in:'.implode(',', array_column(ContentItemType::cases(), 'value')),
            'title' => 'required|string|max:255',
            'vimeo_id' => 'nullable|string|max:64',
            'file_url' => 'nullable|url',
            'body' => 'nullable|string',
            'order' => 'nullable|integer|min:1',
        ]);

        $service->addContentItem($request->user(), $week, $data);

        return back()->with('status', __('offerings.content_added'));
    }
}
