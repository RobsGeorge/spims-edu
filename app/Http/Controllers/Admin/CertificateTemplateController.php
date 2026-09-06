<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CertificateTemplate;
use App\Models\Course;
use App\Services\Credentials\CertificateTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CertificateTemplateController extends Controller
{
    public function index(): View
    {
        return view('admin.certificate-templates.index', [
            'templates' => CertificateTemplate::query()->with('course')->orderBy('locale')->get(),
            'courses' => Course::query()->where('active', true)->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request, CertificateTemplateService $templates): RedirectResponse
    {
        $data = $request->validate([
            'course_id' => ['nullable', 'exists:courses,id'],
            'locale' => ['required', 'in:ar,en,fr'],
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
            'background_path' => ['nullable', 'string', 'max:255'],
            'signature_path' => ['nullable', 'string', 'max:255'],
        ]);

        $templates->upsert($request->user(), $data);

        return back()->with('status', __('completion.certificate_template_saved'));
    }

    /**
     * A sample rendering with placeholder text, so an admin can see the layout
     * without having to issue a real credential first. Uses
     * CertificateTemplateService::previewRender() rather than mocking a
     * Credential model — the placeholder variables (student name, course
     * title, serial) are supplied directly instead of borrowed from relations
     * that don't exist yet.
     */
    public function preview(Request $request, CertificateTemplateService $templates): View
    {
        $data = $request->validate([
            'course_id' => ['nullable', 'exists:courses,id'],
            'locale' => ['required', 'in:ar,en,fr'],
        ]);

        $html = $templates->previewRender($data['course_id'] ?? null, $data['locale']);

        return view('admin.certificate-templates.preview', ['html' => $html]);
    }
}
