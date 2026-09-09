<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SemesterStatus;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Semester;
use App\Services\Offerings\SemesterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SemesterController extends Controller
{
    public function index(Request $request): View
    {
        $years = AcademicYear::query()->with('semesters')->orderByDesc('start_date')->get();
        $selectedYearId = $request->query('year');
        $selectedYear = $years->firstWhere('id', $selectedYearId) ?? $years->first();

        return view('admin.semesters.index', [
            'years'        => $years,
            'selectedYear' => $selectedYear,
            'today'        => now(),
        ]);
    }

    public function storeYear(Request $request, SemesterService $service): RedirectResponse
    {
        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after:start_date',
        ]);

        $service->createYear($request->user(), $data);

        return back()->with('status', __('offerings.year_created'));
    }

    public function storeSemester(Request $request, AcademicYear $year, SemesterService $service): RedirectResponse
    {
        $data = $request->validate([
            'name'                     => 'required|string|max:100',
            'start_date'               => 'required|date',
            'end_date'                 => 'required|date|after:start_date',
            'registration_start'       => 'required|date',
            'registration_end'         => 'required|date|after:registration_start',
            'add_drop_end_week'        => 'required|integer|min:1',
            'last_withdrawal_week'     => 'required|integer|min:1',
            'withdrawal_refund_percent' => 'nullable|numeric|min:0|max:100',
            'status'                   => 'nullable|in:' . implode(',', array_column(SemesterStatus::cases(), 'value')),
        ]);

        $service->createSemester($request->user(), $year, $data);

        return back()->with('status', __('offerings.semester_created'));
    }

    public function editYear(AcademicYear $year): View
    {
        return view('admin.academic-years.edit', [
            'year' => $year,
        ]);
    }

    public function updateYear(Request $request, AcademicYear $year, SemesterService $service): RedirectResponse
    {
        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after:start_date',
        ]);

        $service->updateYear($request->user(), $year, $data);

        return redirect()->route('admin.semesters.index')->with('status', __('offerings.year_updated'));
    }

    public function editSemester(Semester $semester): View
    {
        return view('admin.semesters.edit', [
            'semester' => $semester->load('academicYear'),
            'statuses' => SemesterStatus::cases(),
        ]);
    }

    public function updateSemester(Request $request, Semester $semester, SemesterService $service): RedirectResponse
    {
        $data = $request->validate([
            'name'                     => 'required|string|max:100',
            'start_date'               => 'required|date',
            'end_date'                 => 'required|date|after:start_date',
            'registration_start'       => 'required|date',
            'registration_end'         => 'required|date|after:registration_start',
            'add_drop_end_week'        => 'required|integer|min:1',
            'last_withdrawal_week'     => 'required|integer|min:1',
            'withdrawal_refund_percent' => 'nullable|numeric|min:0|max:100',
            'status'                   => 'required|in:' . implode(',', array_column(SemesterStatus::cases(), 'value')),
        ]);

        $service->updateSemester($request->user(), $semester, $data);

        return redirect()->route('admin.semesters.index')->with('status', __('offerings.semester_updated'));
    }

    /**
     * Transition a semester status via the state machine.
     * POST /admin/semesters/{semester}/transition
     */
    public function transition(Request $request, Semester $semester, SemesterService $service): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:' . implode(',', array_column(SemesterStatus::cases(), 'value')),
        ]);

        $to = SemesterStatus::from($validated['status']);

        $service->transitionStatus($request->user(), $semester, $to);

        return back()->with('status', __('semesters.status_changed'));
    }
}
