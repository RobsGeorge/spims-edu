<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\SuperAdmin\AuditExplorerService;
use App\Support\AuthorizeService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditExplorerController extends Controller
{
    /**
     * @return list<string>
     */
    private function filterKeys(): array
    {
        return ['actor', 'action', 'entity_type', 'entity_id', 'from', 'to', 'request_id'];
    }

    public function index(Request $request, AuditExplorerService $explorer, AuthorizeService $authorize): View
    {
        $actor = $request->user();
        $filters = $request->only($this->filterKeys());
        $logs = $explorer->paginate($actor, $filters);
        $matched = $explorer->matchingCount($actor, $filters);

        return view('superadmin.audit.index', [
            'logs' => $logs,
            'filters' => $filters,
            'matched' => $matched,
            'exportCap' => $explorer->exportCap(),
            'retentionDays' => $explorer->retentionDays(),
            'protectedPrefixes' => $explorer->protectedPrefixes(),
            'canExport' => $authorize->allows($actor, 'audit.export'),
            'prefixHints' => ['users.', 'roles.', 'rbac.', 'auth.', 'theme.', 'enrollment.', 'finance.'],
        ]);
    }

    public function show(Request $request, AuditLog $auditLog, AuthorizeService $authorize): View
    {
        $authorize->authorize($request->user(), 'audit.view');
        $auditLog->load('actor');

        return view('superadmin.audit.show', [
            'log' => $auditLog,
            'canOpenDossier' => $auditLog->actor_id !== null
                && $authorize->allows($request->user(), 'users.manage'),
        ]);
    }

    public function export(Request $request, AuditExplorerService $explorer): StreamedResponse
    {
        $filters = $request->only($this->filterKeys());
        $result = $explorer->export($request->user(), $filters);

        $filename = 'spims-audit-'.now()->format('Ymd-His').'.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $localeHeaders = [
            __('audit.col_when'),
            __('audit.col_actor'),
            __('audit.col_actor_role'),
            __('audit.col_action'),
            __('audit.col_entity_type'),
            __('audit.col_entity_id'),
            __('audit.col_request_id'),
            __('audit.col_ip'),
            __('audit.col_user_agent'),
            __('audit.col_before'),
            __('audit.col_after'),
        ];

        return response()->streamDownload(function () use ($result, $localeHeaders): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $localeHeaders);
            foreach ($result['rows'] as $log) {
                fputcsv($out, [
                    $log->created_at?->toIso8601String(),
                    $log->actor?->email,
                    $log->actor_role,
                    $log->action,
                    $log->entity_type,
                    $log->entity_id,
                    $log->request_id,
                    $log->ip,
                    $log->user_agent,
                    $log->before === null ? '' : json_encode($log->before, JSON_UNESCAPED_UNICODE),
                    $log->after === null ? '' : json_encode($log->after, JSON_UNESCAPED_UNICODE),
                ]);
            }
            fclose($out);
        }, $filename, $headers);
    }
}
