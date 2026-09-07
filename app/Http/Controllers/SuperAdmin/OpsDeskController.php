<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\SuperAdmin\OpsDeskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OpsDeskController extends Controller
{
    public function index(Request $request, OpsDeskService $ops): View
    {
        $ops->authorizeJobs($request->user());

        return view('superadmin.ops.index', [
            'failed' => $ops->failedJobs(),
            'backup' => $ops->backupSnapshot(),
            'schedule' => $ops->scheduledEvents(),
            'session' => $ops->sessionInfo(),
            'queueConnection' => (string) config('queue.default'),
        ]);
    }

    public function retry(Request $request, string $uuid, OpsDeskService $ops): RedirectResponse
    {
        $this->assertUuid($uuid);
        $attempts = $ops->retry($request->user(), $uuid);

        return back()->with('status', __('ops.retried', ['attempts' => $attempts]));
    }

    public function destroy(Request $request, string $uuid, OpsDeskService $ops): RedirectResponse
    {
        $this->assertUuid($uuid);
        $ops->delete($request->user(), $uuid);

        return back()->with('status', __('ops.deleted'));
    }

    public function backup(Request $request, OpsDeskService $ops): RedirectResponse
    {
        $result = $ops->backupNow($request->user());
        $key = $result['queued'] ? 'ops.backup_queued' : 'ops.backup_ran';

        return back()->with('status', __($key, [
            'driver' => $result['driver'],
        ]));
    }

    private function assertUuid(string $uuid): void
    {
        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $uuid)) {
            abort(404);
        }
    }
}
