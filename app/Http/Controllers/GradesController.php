<?php

namespace App\Http\Controllers;

use App\Services\Learning\StudentGradesService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GradesController extends Controller
{
    public function index(Request $request, StudentGradesService $grades): View
    {
        return view('grades.index', [
            'rows' => $grades->forStudent($request->user()),
        ]);
    }
}
