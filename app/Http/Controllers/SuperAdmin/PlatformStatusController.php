<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\SuperAdmin\PlatformStatusService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlatformStatusController extends Controller
{
    public function index(Request $request, PlatformStatusService $status): View
    {
        $status->authorize($request->user());

        return view('superadmin.status.index', $status->snapshot());
    }
}
