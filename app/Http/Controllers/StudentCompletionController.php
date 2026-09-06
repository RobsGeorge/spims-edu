<?php

namespace App\Http\Controllers;

use App\Enums\CompletionOutcome;
use App\Exceptions\AuthorizationException;
use App\Models\CourseOffering;
use App\Services\Completion\CompletionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentCompletionController extends Controller
{
    public function show(Request $request, CourseOffering $offering, CompletionService $completion): View
    {
        try {
            $result = $completion->own($request->user(), $offering);
        } catch (AuthorizationException) {
            abort(404);
        }

        $offering->loadMissing('course');

        return view('completion.own', [
            'offering' => $offering,
            'result' => $result,
            'outcome' => $result?->outcome ?? CompletionOutcome::Pending,
            'metCriteria' => $result?->met_criteria ?? [],
            'evaluatedAt' => $result?->evaluated_at,
        ]);
    }
}
