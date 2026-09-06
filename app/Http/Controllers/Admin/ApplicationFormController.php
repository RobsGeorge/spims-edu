<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FormFieldType;
use App\Http\Controllers\Controller;
use App\Models\ApplicationForm;
use App\Models\ApplicationFormField;
use App\Models\Program;
use App\Services\Admissions\ApplicationFormService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApplicationFormController extends Controller
{
    public function index(): View
    {
        return view('admin.application-forms.index', [
            'forms' => ApplicationForm::query()->with('program')->latest('id')->get(),
            'programs' => Program::query()->where('active', true)->orderBy('code')->get(),
            'fieldTypes' => FormFieldType::cases(),
        ]);
    }

    public function store(Request $request, ApplicationFormService $service): RedirectResponse
    {
        $data = $request->validate([
            'program_id' => 'required|exists:programs,id',
            'name' => 'required|string|max:255',
            'fields' => 'array',
            'fields.*.label' => 'required|string|max:255',
            'fields.*.type' => 'required|in:'.implode(',', array_column(FormFieldType::cases(), 'value')),
            'fields.*.required' => 'boolean',
        ]);

        $program = Program::query()->findOrFail($data['program_id']);
        $service->create($request->user(), $program, $data);

        return back()->with('status', __('admissions.form_created'));
    }

    public function show(ApplicationForm $form): View
    {
        $form->load(['program', 'fields']);

        return view('admin.application-forms.show', [
            'form' => $form,
            'fieldTypes' => FormFieldType::cases(),
        ]);
    }

    public function update(Request $request, ApplicationForm $form, ApplicationFormService $service): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'active' => 'sometimes|boolean',
        ]);
        $data['active'] = $request->boolean('active');

        $service->update($request->user(), $form, $data);

        return redirect()->route('admin.application-forms.show', $form)->with('status', __('admissions.form_updated'));
    }

    public function addField(Request $request, ApplicationForm $form, ApplicationFormService $service): RedirectResponse
    {
        $data = $request->validate([
            'label' => 'required|string|max:255',
            'type' => 'required|in:'.implode(',', array_column(FormFieldType::cases(), 'value')),
            'required' => 'sometimes|boolean',
        ]);
        $data['required'] = $request->boolean('required');

        $service->addField($request->user(), $form, $data);

        return back()->with('status', __('admissions.field_added'));
    }

    public function deactivateField(Request $request, ApplicationForm $form, ApplicationFormField $field, ApplicationFormService $service): RedirectResponse
    {
        $service->deactivateField($request->user(), $form, $field);

        return back()->with('status', __('admissions.field_deactivated'));
    }
}
