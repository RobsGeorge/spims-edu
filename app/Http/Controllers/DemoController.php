<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AuditLogWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DemoController extends Controller
{
    /**
     * Abort with 403 if demo mode is disabled.
     * In production this is always false regardless of env.
     */
    private function requireDemoMode(): void
    {
        if (! config('spims.demo_mode')) {
            abort(403, __('demo.disabled'));
        }
    }

    /**
     * GET /demo/guide — per-role checklist with deep links.
     */
    public function guide(): View
    {
        $this->requireDemoMode();

        $guideLinks = $this->buildGuideLinks();

        return view('demo.guide', ['guideLinks' => $guideLinks]);
    }

    /**
     * POST /demo/login — quick-login as a seeded demo user.
     * Rate-limited at the configured requests-per-minute per IP.
     * Writes an AuditLog entry on every call.
     */
    public function login(Request $request, AuditLogWriter $audit): RedirectResponse
    {
        $this->requireDemoMode();

        $validated = $request->validate([
            'role' => ['required', 'string', 'in:student,instructor,admin'],
        ]);

        $roleMap = [
            'student'    => 'student1@spims.test',
            'instructor' => 'ins1@spims.test',
            'admin'      => 'adm@spims.test',
        ];

        $email = $roleMap[$validated['role']];

        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            abort(422, __('demo.user_not_found'));
        }

        // Write audit log before session regeneration so actor is captured.
        $audit->write(
            actor: $user,
            action: 'demo.quick_login',
            entityType: 'User',
            entityId: (string) $user->id,
            after: ['role' => $validated['role'], 'email' => $email],
            actorRole: $validated['role'],
        );

        Auth::login($user, false);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    /**
     * Build the guide link data for each role.
     * All routes here must exist (validated in tests).
     *
     * @return array<string, list<array{label_key: string, url: string}>>
     */
    private function buildGuideLinks(): array
    {
        return [
            'student' => [
                ['label_key' => 'demo.guide_link_dashboard',   'url' => route('dashboard')],
                ['label_key' => 'demo.guide_link_catalog',     'url' => route('catalog.index')],
                ['label_key' => 'demo.guide_link_enrollments', 'url' => route('enrollments.index')],
                ['label_key' => 'demo.guide_link_grades',      'url' => route('grades.index')],
                ['label_key' => 'demo.guide_link_finance',     'url' => route('finance.index')],
                ['label_key' => 'demo.guide_link_attendance',  'url' => route('attendance.index')],
                ['label_key' => 'demo.guide_link_announcements', 'url' => route('announcements.index')],
            ],
            'instructor' => [
                ['label_key' => 'demo.guide_link_dashboard',   'url' => route('dashboard')],
                ['label_key' => 'demo.guide_link_teach',       'url' => route('teach.index')],
                ['label_key' => 'demo.guide_link_catalog',     'url' => route('catalog.index')],
                ['label_key' => 'demo.guide_link_announcements', 'url' => route('announcements.index')],
            ],
            'admin' => [
                ['label_key' => 'demo.guide_link_admin_users',     'url' => route('admin.users.index')],
                ['label_key' => 'demo.guide_link_admin_offerings', 'url' => route('admin.offerings.index')],
                ['label_key' => 'demo.guide_link_admin_programs',  'url' => route('admin.programs.index')],
                ['label_key' => 'demo.guide_link_admin_finance',   'url' => route('admin.finance.index')],
                ['label_key' => 'demo.guide_link_admin_reports',   'url' => route('admin.reports.index')],
            ],
        ];
    }
}
