<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Communications\CommunicationReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CommunicationAdminController extends Controller
{
    public function __construct(
        private readonly CommunicationReportService $report,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->only(['type', 'channel', 'status', 'recipient_id', 'locale', 'from', 'to']);

        return view('admin.communications.report', [
            'logs' => $this->report->paginate($request->user(), $filters),
            'filters' => $filters,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        return $this->report->exportCsv(
            $request->user(),
            $request->only(['type', 'channel', 'status', 'recipient_id', 'locale', 'from', 'to'])
        );
    }
}
