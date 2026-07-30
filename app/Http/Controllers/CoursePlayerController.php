<?php

namespace App\Http\Controllers;

use App\Models\CourseOffering;
use App\Models\Week;
use App\Services\Learning\CoursePlayerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CoursePlayerController extends Controller
{
    public function show(Request $request, CourseOffering $offering, CoursePlayerService $player): View
    {
        $payload = $player->playerPayload($request->user(), $offering);

        return view('courses.player', $payload);
    }

    public function completeWeek(
        Request $request,
        CourseOffering $offering,
        Week $week,
        CoursePlayerService $player
    ): RedirectResponse {
        $player->completeWeek($request->user(), $offering, $week);

        return back()->with('status', __('learning.week_completed'));
    }
}
