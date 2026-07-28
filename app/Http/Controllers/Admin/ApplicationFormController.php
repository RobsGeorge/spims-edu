<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FormFieldType;
use App\Http\Controllers\Controller;
use App\Models\ApplicationForm;
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
}
