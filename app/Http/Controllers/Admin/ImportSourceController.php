<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImportGradeMapping;
use App\Models\ImportSource;
use App\Services\Import\ImportSourceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ImportSourceController extends Controller
{
    public function index(): View
    {
        return view('admin.imports.sources', [
            'sources' => ImportSource::query()->withCount('gradeMappings')->with('gradeMappings')->orderBy('precedence')->get(),
        ]);
    }

    public function store(Request $request, ImportSourceService $sources): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'alpha_dash', 'unique:import_sources,code'],
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['required', 'in:SIS,LMS'],
            'precedence' => ['required', 'integer', 'min:1', 'max:99'],
            'gpa_scale_max' => ['required', 'numeric', 'min:1', 'max:20'],
            'default_currency' => ['required', 'in:EGP,USD'],
            'timezone' => ['required', 'string', 'max:60'],
        ]);
        $data['code'] = strtoupper($data['code']);

        $sources->upsert($request->user(), null, $data);

        return back()->with('status', __('import.source_saved'));
    }

    public function update(Request $request, ImportSource $source, ImportSourceService $sources): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['required', 'in:SIS,LMS'],
            'precedence' => ['required', 'integer', 'min:1', 'max:99'],
            'gpa_scale_max' => ['required', 'numeric', 'min:1', 'max:20'],
            'default_currency' => ['required', 'in:EGP,USD'],
            'timezone' => ['required', 'string', 'max:60'],
            'active' => ['sometimes', 'boolean'],
        ]);
        $data['active'] = $request->boolean('active');

        $sources->upsert($request->user(), $source, $data);

        return back()->with('status', __('import.source_saved'));
    }

    public function storeGradeMapping(Request $request, ImportSource $source, ImportSourceService $sources): RedirectResponse
    {
        $data = $this->validateGradeMapping($request);
        $sources->upsertGradeMapping($request->user(), $source, null, $data);

        return back()->with('status', __('import.grade_mapping_saved'));
    }

    public function updateGradeMapping(Request $request, ImportSource $source, ImportGradeMapping $grade, ImportSourceService $sources): RedirectResponse
    {
        $data = $this->validateGradeMapping($request);
        $sources->upsertGradeMapping($request->user(), $source, $grade, $data);

        return back()->with('status', __('import.grade_mapping_saved'));
    }

    public function destroyGradeMapping(Request $request, ImportSource $source, ImportGradeMapping $grade, ImportSourceService $sources): RedirectResponse
    {
        $sources->deleteGradeMapping($request->user(), $grade);

        return back()->with('status', __('import.grade_mapping_deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateGradeMapping(Request $request): array
    {
        $data = $request->validate([
            'legacy_letter' => ['nullable', 'string', 'max:20'],
            'min_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'max_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'spims_letter' => ['required', 'string', 'max:10'],
            'gpa_points' => ['required', 'numeric', 'min:0', 'max:10'],
            'is_passing' => ['sometimes', 'boolean'],
            'counts_toward_gpa' => ['sometimes', 'boolean'],
        ]);
        $data['is_passing'] = $request->boolean('is_passing');
        $data['counts_toward_gpa'] = $request->boolean('counts_toward_gpa');

        return $data;
    }
}
