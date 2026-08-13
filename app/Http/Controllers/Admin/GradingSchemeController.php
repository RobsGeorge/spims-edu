<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GradingScheme;
use App\Services\Academics\GradingSchemeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GradingSchemeController extends Controller
{
    public function index(): View
    {
        return view('admin.grading-schemes.index', [
            'schemes' => GradingScheme::query()->with('bands')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, GradingSchemeService $service): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'is_default' => 'boolean',
            'bands' => 'required|array|min:1',
            'bands.*.letter' => 'required|string|max:8',
            'bands.*.min_percent' => 'required|numeric|min:0|max:100',
            'bands.*.max_percent' => 'required|numeric|min:0|max:100',
            'bands.*.gpa_points' => 'required|numeric|min:0|max:4',
            'bands.*.is_passing' => 'boolean',
        ]);

        $service->create($request->user(), $data);

        return back()->with('status', __('academics.grading_scheme_created'));
    }

    public function update(Request $request, GradingScheme $gradingScheme, GradingSchemeService $service): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'is_default' => 'boolean',
            'bands' => 'required|array|min:1',
            'bands.*.letter' => 'required|string|max:8',
            'bands.*.min_percent' => 'required|numeric|min:0|max:100',
            'bands.*.max_percent' => 'required|numeric|min:0|max:100',
            'bands.*.gpa_points' => 'required|numeric|min:0|max:4',
            'bands.*.is_passing' => 'boolean',
        ]);

        $service->updateBands($request->user(), $gradingScheme, $data);

        return back()->with('status', __('academics.grading_scheme_updated'));
    }
}
