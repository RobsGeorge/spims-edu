<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\SuperAdmin\AccessMapService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccessMapController extends Controller
{
    public function index(Request $request, AccessMapService $access): View
    {
        $access->authorize($request->user());
        $query = $request->string('q')->toString();

        return view('superadmin.access.index', $access->snapshot($query));
    }

    public function csv(Request $request, AccessMapService $access): StreamedResponse
    {
        $access->authorize($request->user());

        return $access->exportCsv($request->user(), $request->string('q')->toString());
    }
}
