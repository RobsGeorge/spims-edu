<?php

namespace App\Http\Controllers;

use App\Models\CourseOffering;
use App\Models\Week;
use App\Services\Learning\CoursePlayerService;
use App\Services\Learning\OfferingAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CoursePlayerController extends Controller
{
    public function __construct(
        private readonly LearnController $learn,
        private readonly OfferingAccessService $access,
        private readonly CoursePlayerService $player,
    ) {}

    public function show(Request $request, CourseOffering $offering): View
    {
        $this->access->assertCanAccessOffering($request->user(), $offering);

        return $this->learn->offering($request, $offering);
    }

    public function completeWeek(
        Request $request,
        CourseOffering $offering,
        Week $week,
    ): RedirectResponse {
        $this->player->completeWeek($request->user(), $offering, $week);

        return back()->with('status', __('learning.week_completed'));
    }
}
