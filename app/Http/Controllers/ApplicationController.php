<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ApplicationForm;
use App\Models\Program;
use App\Services\Admissions\ApplicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApplicationController extends Controller
{
    public function index(Request $request): View
    {
        return view('applications.index', [
            'applications' => Application::query()
                ->where('applicant_id', $request->user()->id)
                ->with('program')
                ->latest()
                ->get(),
            'programs' => Program::query()->where('active', true)->with(['applicationForms' => fn ($q) => $q->where('active', true)])->get(),
        ]);
    }

    public function create(Request $request, ApplicationForm $form, ApplicationService $service): View
    {
        $application = $service->start($request->user(), $form->load('fields', 'program'));

        // Prefill from prior applications' common field labels.
        $prior = Application::query()
            ->where('applicant_id', $request->user()->id)
            ->where('id', '!=', $application->id)
            ->with('values.field')
            ->latest()
            ->get();

        $prefill = [];
        foreach ($form->fields as $field) {
            foreach ($prior as $app) {
                $match = $app->values->first(fn ($v) => $v->field?->label === $field->label);
                if ($match) {
                    $prefill[$field->id] = $match->value;
                    break;
                }
            }
        }

        return view('applications.form', [
            'form' => $form,
            'application' => $application->load('values'),
            'prefill' => $prefill,
        ]);
    }

    public function store(Request $request, Application $application, ApplicationService $service): RedirectResponse
    {
        $data = $request->validate([
            'answers' => 'array',
            'answers.*' => 'nullable',
            'files' => 'array',
            'files.*' => 'nullable|file|max:10240',
            'submit' => 'nullable|boolean',
        ]);

        $service->saveAnswers(
            $request->user(),
            $application,
            $data['answers'] ?? [],
            $request->file('files', []) ?? []
        );

        if ($request->boolean('submit')) {
            // Reload values after save
            $application->refresh()->load('values', 'form.fields');
            $service->submit($request->user(), $application);
        }

        return redirect()->route('applications.index')->with('status', __('admissions.application_saved'));
    }
}
