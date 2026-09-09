<?php

namespace App\Http\Controllers;

use App\Enums\ProgramType;
use App\Enums\RoleType;
use App\Models\Course;
use App\Models\Program;
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
            ->limit(4)
            ->get();

        // Group active programs by type; only include tiers that have programs.
        $allPrograms = Program::query()->where('active', true)->get();
        $programTiers = collect(ProgramType::cases())
            ->map(function (ProgramType $type) use ($allPrograms) {
                $programs = $allPrograms->where('type', $type);
                return [
                    'type'     => $type,
                    'programs' => $programs,
                    'count'    => $programs->count(),
                ];
            })
            ->filter(fn ($tier) => $tier['count'] > 0)
            ->values();

        return view('home', [
            'theme'        => Theme::query()->where('is_active', true)->first(),
            'featured'     => $featured,
            'programTiers' => $programTiers,
            'stats'        => [
                'students' => User::query()
                    ->whereHas('roles', fn ($q) => $q->where('role', RoleType::Student))
                    ->count(),
                'courses' => Course::query()->where('active', true)->count(),
            ],
        ]);
    }
}
