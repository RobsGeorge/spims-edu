<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\SystemDocs\SystemDocsCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SystemDocsPublishController extends Controller
{
    public function __construct(
        private readonly SystemDocsCatalog $catalog,
    ) {}

    public function edit(): View
    {
        return view('superadmin.system-docs', [
            'guestPublished' => $this->catalog->isGuestPublished(),
            'clientPages' => $this->catalog->allPages()->where('guest_eligible', true)->values(),
            'technicalPages' => $this->catalog->allPages()->where('audience', 'technical')->values(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $enabled = $request->boolean('guest_published');
        $this->catalog->setGuestPublished($request->user(), $enabled);

        return back()->with(
            'status',
            $enabled
                ? __('system_docs.publish_enabled')
                : __('system_docs.publish_disabled')
        );
    }
}
