<?php

namespace App\Http\Controllers;

use App\Services\Learning\StudentDashboardService;
use App\Support\NavigationHub;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, StudentDashboardService $dashboard): View
    {
        $user = $request->user();
        $bento = $dashboard->build($user);

        return view('dashboard', array_merge($bento, [
            'hasAcademic' => count(NavigationHub::academicLinks($user)) > 0,
            'hasAdmin' => count(NavigationHub::adminLinks($user)) > 0,
            'hasFinanceAdmin' => NavigationHub::hasFinanceAdmin($user),
            'hasSuperadmin' => NavigationHub::hasSuperadmin($user),
        ]));
    }
}
