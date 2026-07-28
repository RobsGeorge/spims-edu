<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\QuestionBank;
use App\Services\Assessment\AssessmentService;
use App\Services\Assessment\AttemptService;
use App\Services\Assessment\QuestionBankService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AssessmentAdminController extends Controller
{
    public function banksIndex(Course $course): View
    {
        return view('admin.assessments.banks', [
            'course' => $course,
            'banks' => QuestionBank::query()->where('course_id', $course->id)->withCount('questions')->get(),
        ]);
    }

    public function storeBank(Request $request, Course $course, QuestionBankService $banks): RedirectResponse
    {
        $data = $request->validate(['name' => 'required|string|max:120']);
        $banks->createBank($request->user(), $course, $data['name']);

        return back()->with('status', __('assessment.bank_created'));
    }

    public function storeQuestion(Request $request, QuestionBank $bank, QuestionBankService $banks): RedirectResponse
    {
        $data = $request->validate([
            'type' => 'required|string',
            'prompt' => 'required|string',
            'points' => 'nullable|numeric|min:0',
            'correct_option' => 'nullable|integer|min:0',
            'options' => 'nullable|array',
            'options.*' => 'string',
            'ai_key_points' => 'nullable|string',
        ]);

        $options = [];
        foreach ($data['options'] ?? [] as $i => $text) {
            $options[] = [
                'text' => $text,
                'is_correct' => isset($data['correct_option']) && (int) $data['correct_option'] === $i,
                'order' => $i,
            ];
        }

        $banks->addQuestion($request->user(), $bank, [
            'type' => $data['type'],
            'prompt' => $data['prompt'],
            'points' => $data['points'] ?? 1,
            'options' => $options,
            'ai_key_points' => $data['ai_key_points'] ?? null,
        ]);

        return back()->with('status', __('assessment.question_created'));
    }

    public function createAssessment(CourseOffering $offering): View
    {
        return view('admin.assessments.create', [
            'offering' => $offering->load('course'),
            'banks' => QuestionBank::query()->where('course_id', $offering->course_id)->get(),
            'components' => \App\Models\GradebookComponent::query()->where('offering_id', $offering->id)->get(),
        ]);
    }

    public function storeAssessment(Request $request, CourseOffering $offering, AssessmentService $service): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'mode' => 'required|string',
            'time_limit_minutes' => 'nullable|integer|min:1',
            'attempts_allowed' => 'nullable|integer|min:1',
            'draw_from_bank_id' => 'nullable|exists:question_banks,id',
            'questions_to_draw' => 'nullable|integer|min:1',
            'component_id' => 'nullable|exists:gradebook_components,id',
            'max_points' => 'nullable|numeric|min:1',
            'shuffle_questions' => 'nullable|boolean',
        ]);

        $assessment = $service->create($request->user(), $offering, $data);

        return redirect()->route('admin.assessments.show', $assessment)
            ->with('status', __('assessment.created'));
    }

    public function show(Assessment $assessment): View
    {
        return view('admin.assessments.show', [
            'assessment' => $assessment->load(['offering.course', 'assessmentQuestions.question.options', 'attempts.student']),
        ]);
    }

    public function attachQuestion(Request $request, Assessment $assessment, AssessmentService $service): RedirectResponse
    {
        $data = $request->validate(['question_id' => 'required|exists:questions,id']);
        $service->attachQuestion($request->user(), $assessment, \App\Models\Question::query()->findOrFail($data['question_id']));

        return back()->with('status', __('assessment.question_attached'));
    }

    public function release(Request $request, Assessment $assessment, AssessmentService $service): RedirectResponse
    {
        $service->release($request->user(), $assessment);

        return back()->with('status', __('assessment.released'));
    }

    public function overrideScore(Request $request, \App\Models\AttemptAnswer $answer, AttemptService $attempts): RedirectResponse
    {
        $data = $request->validate([
            'final_score' => 'required|numeric|min:0',
            'feedback' => 'nullable|string',
        ]);

        $attempts->overrideScore($request->user(), $answer, (float) $data['final_score'], $data['feedback'] ?? null);

        return back()->with('status', __('assessment.score_saved'));
    }
}
