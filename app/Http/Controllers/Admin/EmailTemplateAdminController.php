<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CourseOffering;
use App\Models\EmailTemplate;
use App\Services\Communications\EmailTemplateService;
use App\Support\AuthorizeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailTemplateAdminController extends Controller
{
    public function __construct(
        private readonly EmailTemplateService $templates,
        private readonly AuthorizeService $authorize,
    ) {}

    public function index(Request $request): View
    {
        $offering = $request->filled('offering_id')
            ? CourseOffering::query()->find($request->string('offering_id'))
            : null;

        $this->authorize->authorize($request->user(), 'email_templates.manage', $offering);

        return view('admin.communications.templates', [
            'templates' => EmailTemplate::query()->orderBy('key')->orderBy('locale')->get(),
            'preview' => $request->session()->get('template_preview'),
            'offering' => $offering,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:80'],
            'locale' => ['required', 'in:ar,en,fr'],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:20000'],
            'scope_type' => ['nullable', 'in:course,offering'],
            'scope_id' => ['nullable', 'string', 'max:40'],
            'offering_id' => ['nullable', 'string', 'max:40'],
        ]);

        $offering = ! empty($data['offering_id'])
            ? CourseOffering::query()->findOrFail($data['offering_id'])
            : null;

        $this->templates->upsert($request->user(), $data, $offering);

        return back()->with('status', __('communications.template_saved'));
    }

    public function preview(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:80'],
            'locale' => ['required', 'in:ar,en,fr'],
            'offering_id' => ['nullable', 'string', 'max:40'],
            'name' => ['nullable', 'string', 'max:120'],
            'title' => ['nullable', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:2000'],
        ]);

        $offering = ! empty($data['offering_id'])
            ? CourseOffering::query()->findOrFail($data['offering_id'])
            : null;

        $preview = $this->templates->preview(
            $request->user(),
            $data['key'],
            $data['locale'],
            [
                'name' => $data['name'] ?? $request->user()->first_name,
                'title' => $data['title'] ?? 'Preview',
                'body' => $data['body'] ?? 'Preview',
            ],
            $offering,
        );

        return back()->with('template_preview', $preview);
    }
}
