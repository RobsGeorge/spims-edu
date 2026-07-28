<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\User;
use App\Support\AuditLogWriter;
use App\Support\NavigationHub;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class SuperAdminController extends Controller
{
    public function index(): View
    {
        return view('superadmin.index', [
            'sections' => NavigationHub::superadminSections(),
        ]);
    }

    public function security(): View
    {
        $sessionDriver = config('session.driver');
        $sessionCount = null;
        if ($sessionDriver === 'database' && Schema::hasTable('sessions')) {
            $sessionCount = DB::table('sessions')->count();
        }

        return view('superadmin.security', [
            'sessionDriver' => $sessionDriver,
            'sessionCount' => $sessionCount,
        ]);
    }

    public function flushSessions(Request $request, AuditLogWriter $audit): RedirectResponse
    {
        $actor = $request->user();
        $driver = config('session.driver');

        if ($driver === 'database' && Schema::hasTable('sessions')) {
            DB::table('sessions')->where('user_id', '!=', $actor->id)->delete();
            $audit->write($actor, 'superadmin.sessions.flush_others', 'Session', null, null, [
                'kept_user_id' => $actor->id,
            ]);

            return back()->with('status', __('superadmin.sessions_flushed'));
        }

        return back()->with('status', __('superadmin.sessions_flush_unsupported', ['driver' => $driver]));
    }

    public function audit(Request $request): View
    {
        $logs = AuditLog::query()
            ->with('actor')
            ->latest('created_at')
            ->paginate(40);

        return view('superadmin.audit', compact('logs'));
    }

    public function observability(): View
    {
        return view('superadmin.observability', [
            'stats' => [
                'users' => User::query()->count(),
                'courses' => Course::query()->count(),
                'offerings' => CourseOffering::query()->count(),
                'enrollments' => Enrollment::query()->count(),
                'audit_logs' => AuditLog::query()->count(),
            ],
        ]);
    }

    public function scheduledTasks(): View
    {
        $tasks = [
            ['command' => 'assessments:auto-submit-expired', 'schedule' => __('superadmin.schedule_every_minute')],
            ['command' => 'live:send-reminders', 'schedule' => __('superadmin.schedule_every_five')],
            ['command' => 'spims:backup-database', 'schedule' => __('superadmin.schedule_daily_0230')],
        ];

        return view('superadmin.scheduled-tasks', compact('tasks'));
    }

    public function systemTests(): View
    {
        $suites = [
            'Unit', 'Database', 'Auth', 'Audit', 'Smoke', 'Admin', 'Api',
            'Academics', 'Offerings', 'Admissions', 'Enrollment', 'Finance',
            'Assessment', 'Live', 'Credentials', 'Hardening', 'Portal', 'Rbac',
        ];

        return view('superadmin.system-tests', compact('suites'));
    }
}
