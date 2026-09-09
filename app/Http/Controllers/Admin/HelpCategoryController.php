<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HelpCategory;
use App\Services\Admin\HelpAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HelpCategoryController extends Controller
{
    public function index(): View
    {
        return view('admin.help.categories.index', [
            'categories' => HelpCategory::query()
                ->withCount('articles')
                ->orderBy('sort_order')
                ->orderBy('slug')
                ->get(),
        ]);
    }

    public function store(Request $request, HelpAdminService $service): RedirectResponse
    {
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:80', 'alpha_dash', 'unique:help_categories,slug'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_published' => ['sometimes', 'boolean'],
        ]);

        $data['is_published'] = $request->boolean('is_published');

        $service->createCategory($request->user(), $data);

        return redirect()
            ->route('admin.help.categories.index')
            ->with('status', __('help.admin_category_saved'));
    }

    public function edit(HelpCategory $category): View
    {
        return view('admin.help.categories.edit', [
            'category' => $category,
        ]);
    }

    public function update(Request $request, HelpCategory $category, HelpAdminService $service): RedirectResponse
    {
        $data = $request->validate([
            'slug' => [
                'required',
                'string',
                'max:80',
                'alpha_dash',
                Rule::unique('help_categories', 'slug')->ignore($category->id),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_published' => ['sometimes', 'boolean'],
        ]);

        $data['is_published'] = $request->boolean('is_published');

        $service->updateCategory($request->user(), $category, $data);

        return redirect()
            ->route('admin.help.categories.index')
            ->with('status', __('help.admin_category_saved'));
    }

    public function destroy(Request $request, HelpCategory $category, HelpAdminService $service): RedirectResponse
    {
        if ($category->articles()->exists()) {
            return back()->withErrors([
                'category' => __('help.admin_category_has_articles'),
            ]);
        }

        $service->deleteCategory($request->user(), $category);

        return redirect()
            ->route('admin.help.categories.index')
            ->with('status', __('help.admin_category_deleted'));
    }
}
