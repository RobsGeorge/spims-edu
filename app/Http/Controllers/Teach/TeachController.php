<?php

namespace App\Http\Controllers\Teach;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Services\Teach\TeachAccessService;
use App\Support\AuditLogWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeachController extends Controller
{
    public function __construct(
        private readonly TeachAccessService $teachAccess,
        private readonly AuditLogWriter $audit,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($this->teachAccess->canTeach($user), 403);

        return view('teach.index', [
            'offerings' => $this->teachAccess->offeringsFor($user),
        ]);
    }

    public function show(Request $request, CourseOffering $offering): View
    {
        $user = $request->user();
        abort_unless($this->teachAccess->canTeach($user), 403);
        $this->teachAccess->assertCanTeachOffering($user, $offering);

        $offering->load(['course', 'semester', 'weeks.contentItems', 'staff.user']);
        $tab = $request->query('tab', 'content');
        $roster = Enrollment::query()
            ->where('offering_id', $offering->id)
            ->with('student')
            ->orderBy('enrolled_at')
            ->get();

        return view('teach.show', [
            'offering' => $offering,
            'tab' => $tab,
            'roster' => $roster,
            'rosterCount' => $roster->count(),
            'announcements' => Announcement::query()
                ->where('offering_id', $offering->id)
                ->orderByDesc('created_at')
                ->limit(20)
                ->get(),
        ]);
    }

    public function storeAnnouncement(Request $request, CourseOffering $offering): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->teachAccess->canTeach($user), 403);
        $this->teachAccess->assertCanTeachOffering($user, $offering);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:10000'],
        ]);

        $this->audit->withAudit($user, 'announcement.create', function () use ($data, $offering, $user) {
            return Announcement::query()->create([
                'offering_id' => $offering->id,
                'author_id' => $user->id,
                'title' => $data['title'],
                'body' => $data['body'],
            ]);
        }, Announcement::class);

        return redirect()
            ->route('teach.show', ['offering' => $offering, 'tab' => 'announcements'])
            ->with('status', __('teach.announcement_saved'));
    }
}
