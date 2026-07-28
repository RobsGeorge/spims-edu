<?php

namespace App\Http\Controllers;

use App\Support\NavigationHub;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HubController extends Controller
{
    public function academic(Request $request): View
    {
        $user = $request->user();

        return view('hubs.academic', [
            'links' => NavigationHub::academicLinks($user),
        ]);
    }

    public function learning(Request $request): View
    {
        return view('hubs.learning', [
            'links' => NavigationHub::learningLinks($request->user()),
        ]);
    }

    public function admin(Request $request): View
    {
        return view('hubs.admin', [
            'links' => NavigationHub::adminLinks($request->user()),
        ]);
    }

    public function finance(Request $request): View
    {
        return view('hubs.finance', [
            'links' => NavigationHub::financeLinks($request->user()),
        ]);
    }
}
