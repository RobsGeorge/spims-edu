<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Services\Reports\AcademicStandingService;
use App\Services\Reports\ReportService;
use App\Support\AuthorizeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly AcademicStandingService $standing,
        private readonly AuthorizeService $authorize,
    ) {}

    public function index(Request $request): View
    {
        $this->reports->authorizeView($request->user());

        return view('admin.reports.index', [
            'canManageStanding' => $this->authorize->allows($request->user(), 'academic_standing.manage'),
        ]);
    }

    public function headcount(Request $request): View
    {
        return $this->show($request, 'headcount');
    }

    public function admissions(Request $request): View
    {
        return $this->show($request, 'admissions');
    }

    public function attendance(Request $request): View
    {
        return $this->show($request, 'attendance');
    }

    public function grades(Request $request): View
    {
        return $this->show($request, 'grades');
    }

    public function finance(Request $request): View
    {
        $this->reports->authorizeFinance($request->user());
        $summary = $this->reports->financeSummary();

        return view('admin.reports.finance', [
            'outstanding' => $summary['outstanding'],
            'paidRevenue' => $summary['paidRevenue'],
            'aging' => $this->reports->paginate('finance'),
            'headers' => $this->reports->headers('finance'),
        ]);
    }

    public function standing(Request $request): View
    {
        $this->reports->authorizeReport($request->user(), 'standing');

        return view('admin.reports.show', [
            'report' => 'standing',
            'title' => __('reports.standing_title'),
            'subtitle' => __('reports.standing_desc'),
            'headers' => $this->reports->headers('standing'),
            'rows' => $this->reports->paginate('standing'),
            'canManageStanding' => $this->authorize->allows($request->user(), 'academic_standing.manage'),
        ]);
    }

    public function standingThresholds(Request $request): View
    {
        $this->authorize->authorize($request->user(), 'academic_standing.manage');

        $programs = Program::query()->where('active', true)->orderBy('code')->get();

        return view('admin.reports.standing-thresholds', [
            'thresholds' => $this->standing->thresholds(),
            'programThresholds' => $programs->map(fn (Program $program) => [
                'program' => $program,
                'thresholds' => $this->standing->thresholdsFor($program),
            ]),
        ]);
    }

    public function updateStandingThresholds(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'good_min' => ['required', 'integer', 'min:0', 'max:400'],
            'suspension_below' => ['required', 'integer', 'min:0', 'max:400', 'lt:good_min'],
        ], [
            'suspension_below.lt' => __('reports.thresholds_order_invalid'),
        ]);

        $this->standing->updateThresholds($request->user(), $data);

        return redirect()
            ->route('admin.reports.standing.thresholds')
            ->with('status', __('reports.thresholds_saved'));
    }

    public function csv(Request $request, string $report): StreamedResponse
    {
        return $this->reports->exportCsv($request->user(), $report);
    }

    private function show(Request $request, string $report): View
    {
        $this->reports->authorizeReport($request->user(), $report);

        return view('admin.reports.show', [
            'report' => $report,
            'title' => __('reports.'.$report.'_title'),
            'subtitle' => __('reports.'.$report.'_desc'),
            'headers' => $this->reports->headers($report),
            'rows' => $this->reports->paginate($report),
        ]);
    }
}
