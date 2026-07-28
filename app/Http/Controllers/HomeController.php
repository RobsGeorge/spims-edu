<?php

namespace App\Http\Controllers;

use App\Models\Theme;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        return view('home', [
            'theme' => Theme::query()->where('is_active', true)->first(),
        ]);
    }
}
