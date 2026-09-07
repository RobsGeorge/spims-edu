<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SuperAdmin\ImpersonationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ImpersonationController extends Controller
{
    public function start(Request $request, User $user, ImpersonationService $impersonation): RedirectResponse
    {
        $request->validate([
            'confirm' => 'accepted',
        ]);

        $impersonation->start($request->user(), $user);

        return redirect()->route('dashboard')->with('status', __('people.impersonate_started', [
            'name' => $user->displayName(),
        ]));
    }

    public function stop(ImpersonationService $impersonation): RedirectResponse
    {
        $impersonation->stop();

        return redirect()->route('superadmin.index')->with('status', __('people.impersonate_stopped'));
    }
}
