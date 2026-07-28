<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OfferingStatus;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Services\Offerings\SemesterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SemesterController extends Controller
{
    public function index(): View
    {
        return view('admin.semesters.index', [
            'years' => AcademicYear::query()->with('semesters')->orderByDesc('start_date')->get(),
        ]);
    }

    public function storeYear(Request $request, SemesterService $service): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        $service->createYear($request->user(), $data);

        return back()->with('status', __('offerings.year_created'));
    }

    public function storeSemester(Request $request, AcademicYear $year, SemesterService $service): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'registration_start' => 'required|date',
            'registration_end' => 'required|date|after:registration_start',
            'add_drop_end_week' => 'required|integer|min:1',
            'last_withdrawal_week' => 'required|integer|min:1',
            'withdrawal_refund_percent' => 'nullable|numeric|min:0|max:100',
            'status' => 'nullable|in:'.implode(',', array_column(OfferingStatus::cases(), 'value')),
        ]);

        $service->createSemester($request->user(), $year, $data);

        return back()->with('status', __('offerings.semester_created'));
    }
}
