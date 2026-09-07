<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\SuperAdmin\SchoolReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SchoolReportController extends Controller
{
    public function index(Request $request, SchoolReportService $reports): View
    {
        $reports->authorize($request->user());
        $range = $reports->resolveRange($request->query('from'), $request->query('to'));

        return view('superadmin.reports.index', [
            'reports' => $reports->catalog(),
            'range' => $range,
            'rangeQuery' => $this->rangeQuery($range),
        ]);
    }

    public function show(Request $request, string $report, SchoolReportService $reports): View
    {
        $reports->authorize($request->user());
        if (! $reports->isAvailable($report)) {
            abort(404);
        }

        $range = $reports->resolveRange($request->query('from'), $request->query('to'));
        $payload = $reports->payload($report, $range['from'], $range['to']);

        return view('superadmin.reports.show', [
            'report' => $report,
            'range' => $range,
            'payload' => $payload,
            'catalog' => $reports->catalog(),
            'rangeQuery' => $this->rangeQuery($range),
        ]);
    }

    public function csv(Request $request, string $report, SchoolReportService $reports): StreamedResponse
    {
        $reports->authorize($request->user());
        if (! $reports->isAvailable($report)) {
            abort(404);
        }

        $range = $reports->resolveRange($request->query('from'), $request->query('to'));

        return $reports->exportCsv($request->user(), $report, $range['from'], $range['to']);
    }

    /**
     * @param  array{from: \Illuminate\Support\Carbon, to: \Illuminate\Support\Carbon}  $range
     * @return array{from: string, to: string}
     */
    private function rangeQuery(array $range): array
    {
        return [
            'from' => $range['from']->toDateString(),
            'to' => $range['to']->toDateString(),
        ];
    }
}
