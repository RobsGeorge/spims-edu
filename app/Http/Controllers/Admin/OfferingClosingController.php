<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CourseOffering;
use App\Services\Completion\CompletionService;
use App\Services\Completion\OfferingClosingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OfferingClosingController extends Controller
{
    public function show(Request $request, CourseOffering $offering, OfferingClosingService $closing, CompletionService $completion): View
    {
        $offering->load('course');

        return view('admin.offering-closing.show', [
            'offering' => $offering,
            'status' => $closing->statusFor($offering),
            'results' => $completion->cohort($request->user(), $offering),
        ]);
    }

    public function evaluate(Request $request, CourseOffering $offering, CompletionService $completion): RedirectResponse
    {
        $completion->evaluate($request->user(), $offering);

        return back()->with('status', __('completion.evaluated'));
    }

    public function lock(Request $request, CourseOffering $offering, OfferingClosingService $closing): RedirectResponse
    {
        try {
            $closing->lockGrading($request->user(), $offering);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('status', __('completion.grading_locked'));
    }

    public function graceMarks(Request $request, CourseOffering $offering, OfferingClosingService $closing): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'array'],
            'student_id.*' => ['string', 'exists:users,id'],
            'amount' => ['required', 'array'],
            'amount.*' => ['numeric'],
        ]);

        $map = [];
        foreach ($data['student_id'] as $index => $studentId) {
            $amount = (float) ($data['amount'][$index] ?? 0);
            if ($amount != 0.0) {
                $map[$studentId] = $amount;
            }
        }

        try {
            $closing->applyGraceMarks($request->user(), $offering, $map);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('status', __('completion.grace_marks_applied'));
    }

    public function announce(Request $request, CourseOffering $offering, OfferingClosingService $closing): RedirectResponse
    {
        try {
            $closing->announce($request->user(), $offering);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('status', __('completion.announced'));
    }

    public function close(Request $request, CourseOffering $offering, OfferingClosingService $closing): RedirectResponse
    {
        try {
            $closing->close($request->user(), $offering);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('status', __('completion.closed'));
    }
}
