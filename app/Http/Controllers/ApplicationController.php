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
                ->paginate(20),
            'programs' => Program::query()->where('active', true)->with(['applicationForms' => fn ($q) => $q->where('active', true)])->get(),
        ]);
    }

    public function show(Request $request, Application $application, ApplicationService $service): View
    {
        abort_unless($application->applicant_id === $request->user()->id, 403);

        $application->load(['program', 'form.fields', 'values.field']);

        return view('applications.show', [
            'application' => $application,
            'answers' => $service->displayAnswers($application),
        ]);
    }

    public function create(Request $request, ApplicationForm $form, ApplicationService $service): View|RedirectResponse
    {
        $application = $service->start($request->user(), $form->load('fields', 'program'));

        if (! $application->status->isEditable()) {
            return redirect()->route('applications.show', $application);
        }

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
                    $prefill[$field->id] = $match->getAttribute('value');
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
        abort_unless($application->applicant_id === $request->user()->id, 403);

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
            $application->refresh()->load('values', 'form.fields');
            $service->submit($request->user(), $application);

            return redirect()
                ->route('applications.show', $application)
                ->with('status', __('admissions.application_submitted'));
        }

        return redirect()
            ->route('applications.show', $application)
            ->with('status', __('admissions.application_saved'));
    }

    public function withdraw(Request $request, Application $application, ApplicationService $service): RedirectResponse
    {
        $service->withdraw($request->user(), $application);

        return redirect()->route('applications.index')->with('status', __('admissions.withdrawn'));
    }
}
