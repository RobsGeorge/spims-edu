<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\SuperAdmin\SystemSettingService;
use App\Support\AuthorizeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SystemSettingController extends Controller
{
    public function index(Request $request, SystemSettingService $settings, AuthorizeService $authorize): View
    {
        $authorize->authorize($request->user(), 'system_settings.manage');

        return view('superadmin.config.index', [
            'settings' => $settings->catalog(),
            'integrations' => $settings->integrations(),
            'timezones' => timezone_identifiers_list(),
        ]);
    }

    public function update(Request $request, SystemSettingService $settings): RedirectResponse
    {
        $data = $request->validate([
            'settings' => 'required|array',
        ]);

        $settings->update($request->user(), $data['settings']);

        return back()->with('status', __('system_settings.updated'));
    }
}
