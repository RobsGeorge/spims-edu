<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\SuperAdmin\FeatureFlagService;
use App\Support\AuthorizeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeatureFlagController extends Controller
{
    public function index(Request $request, FeatureFlagService $flags, AuthorizeService $authorize): View
    {
        $authorize->authorize($request->user(), 'features.manage');

        $groups = [];
        foreach ($flags->catalog() as $flag) {
            $groups[$flag['group']][] = $flag;
        }

        return view('superadmin.features.index', [
            'groups' => $groups,
            'groupOrder' => ['public', 'student', 'staff', 's6e'],
        ]);
    }

    public function update(Request $request, string $key, FeatureFlagService $flags): RedirectResponse
    {
        $request->validate([
            'enabled' => 'required|boolean',
        ]);

        $enabled = $request->boolean('enabled');
        $flags->set($request->user(), $key, $enabled);

        return back()->with('status', __('features.updated', [
            'name' => __('features.'.$key),
            'state' => $enabled ? __('features.state_on') : __('features.state_off'),
        ]));
    }
}
