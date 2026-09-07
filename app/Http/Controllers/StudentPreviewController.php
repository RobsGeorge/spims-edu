<?php

namespace App\Http\Controllers;

use App\Models\CourseOffering;
use App\Services\Learning\StudentPreviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StudentPreviewController extends Controller
{
    public function start(Request $request, CourseOffering $offering, StudentPreviewService $preview): RedirectResponse
    {
        $preview->start($request->user(), $offering, $request->headers->get('referer'));

        return redirect()->route('learn.offering', $offering);
    }

    public function stop(Request $request, CourseOffering $offering, StudentPreviewService $preview): RedirectResponse
    {
        $return = $preview->stop();

        return redirect()->to($return ?: route('teach.show', $offering));
    }
}
