<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ComponentKind;
use App\Http\Controllers\Controller;
use App\Models\AssessmentTemplate;
use App\Services\Academics\AssessmentTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AssessmentTemplateController extends Controller
{
    public function index(): View
    {
        return view('admin.assessment-templates.index', [
            'templates' => AssessmentTemplate::query()->with('components')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, AssessmentTemplateService $service): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'is_default' => 'boolean',
            'components' => 'array',
            'components.*.name' => 'required|string|max:255',
            'components.*.weight_percent' => 'required|numeric|min:0|max:100',
            'components.*.kind' => 'required|in:'.implode(',', array_column(ComponentKind::cases(), 'value')),
        ]);

        $service->create($request->user(), $data);

        return back()->with('status', __('academics.template_created'));
    }
}
