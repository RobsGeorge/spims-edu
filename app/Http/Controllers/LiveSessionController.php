<?php

namespace App\Http\Controllers;

use App\Models\LiveSession;
use App\Services\Live\LiveSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LiveSessionController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $offeringIds = \App\Models\Enrollment::query()
            ->where('student_id', $user->id)
            ->pluck('offering_id');

        return view('live.index', [
            'sessions' => LiveSession::query()
                ->whereIn('offering_id', $offeringIds)
                ->where('scheduled_start', '>=', now()->subDay())
                ->orderBy('scheduled_start')
                ->with('offering.course')
                ->get(),
        ]);
    }

    public function join(Request $request, LiveSession $session, LiveSessionService $live): RedirectResponse
    {
        $url = $live->joinUrl($request->user(), $session);

        return redirect()->away($url);
    }
}
