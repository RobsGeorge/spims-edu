<?php

namespace App\Http\Controllers\Teach;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Assessment;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Services\Communications\AnnouncementService;
use App\Services\Teach\TeachAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeachController extends Controller
{
    public function __construct(
        private readonly TeachAccessService $teachAccess,
        private readonly AnnouncementService $announcements,
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

        $offering->load(['course', 'semester', 'weeks.items', 'staff.user']);
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
            'assessments' => Assessment::query()
                ->where('offering_id', $offering->id)
                ->withCount('attempts')
                ->orderBy('title')
                ->get(),
            'announcements' => Announcement::query()
                ->where('offering_id', $offering->id)
                ->with('targets')
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
            'is_banner' => ['nullable', 'boolean'],
            'banner_expires_at' => ['nullable', 'date'],
            'publish' => ['nullable', 'boolean'],
        ]);

        $announcement = $this->announcements->draft($user, $offering, $data);

        if ($request->boolean('publish')) {
            $this->announcements->publish($user, $announcement);
            $status = __('communications.published');
        } else {
            $status = __('communications.draft_saved');
        }

        return redirect()
            ->route('teach.show', ['offering' => $offering, 'tab' => 'announcements'])
            ->with('status', $status);
    }

    public function updateAnnouncement(Request $request, Announcement $announcement): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:10000'],
            'is_banner' => ['nullable', 'boolean'],
            'banner_expires_at' => ['nullable', 'date'],
        ]);

        $this->announcements->update($request->user(), $announcement, $data);

        return redirect()
            ->route('teach.show', ['offering' => $announcement->offering_id, 'tab' => 'announcements'])
            ->with('status', __('communications.updated'));
    }

    public function publishAnnouncement(Request $request, Announcement $announcement): RedirectResponse
    {
        $this->announcements->publish($request->user(), $announcement);

        return redirect()
            ->route('teach.show', ['offering' => $announcement->offering_id, 'tab' => 'announcements'])
            ->with('status', __('communications.published'));
    }

    public function resendAnnouncement(Request $request, Announcement $announcement): RedirectResponse
    {
        $this->announcements->resendEmail($request->user(), $announcement);

        return redirect()
            ->route('teach.show', ['offering' => $announcement->offering_id, 'tab' => 'announcements'])
            ->with('status', __('communications.resent'));
    }
}
