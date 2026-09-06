<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Services\Communications\AnnouncementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function __construct(
        private readonly AnnouncementService $announcements,
    ) {}

    public function index(Request $request): View
    {
        return view('announcements.index', [
            'announcements' => $this->announcements->inboxFor($request->user()),
        ]);
    }

    public function show(Request $request, Announcement $announcement): View
    {
        return view('announcements.show', [
            'announcement' => $this->announcements->showFor($request->user(), $announcement),
        ]);
    }

    public function dismissBanner(Request $request, Announcement $announcement): RedirectResponse
    {
        $this->announcements->dismissBanner($request->user(), $announcement);

        return back()->with('status', __('communications.dismiss_banner'));
    }
}
