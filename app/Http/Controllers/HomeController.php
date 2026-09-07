<?php

namespace App\Http\Controllers;

use App\Enums\RoleType;
use App\Models\Course;
use App\Models\Theme;
use App\Models\User;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $featured = Course::query()
            ->where('active', true)
            ->orderBy('code')
            ->limit(3)
            ->get();

        return view('home', [
            'theme' => Theme::query()->where('is_active', true)->first(),
            'featured' => $featured,
            'stats' => [
                'students' => User::query()
                    ->whereHas('roles', fn ($q) => $q->where('role', RoleType::Student))
                    ->count(),
                'courses' => Course::query()->where('active', true)->count(),
            ],
        ]);
    }
}
