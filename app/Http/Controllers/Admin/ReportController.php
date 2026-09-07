<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Reports\ReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
    ) {}

    public function index(Request $request): View
    {
        $this->reports->authorizeView($request->user());

        return view('admin.reports.index');
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
        return $this->show($request, 'standing');
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
