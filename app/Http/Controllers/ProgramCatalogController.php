<?php

namespace App\Http\Controllers;

use App\Enums\Currency;
use App\Enums\OfferingStatus;
use App\Models\Course;
use App\Models\Program;
use App\Models\ProgramCourse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProgramCatalogController extends Controller
{
    /**
     * Public listing of programs that have at least one published course offering.
     */
    public function index(): View
    {
        $programs = Program::query()
            ->where("active", true)
            ->whereHas("programCourses.course.programCourses", function ($q) {
                // Keep programs that have at least one course with an open offering
            })
            ->whereHas("programCourses.course", function ($q) {
                $q->where("active", true)
                  ->whereHas("programCourses"); // at least one course is enough
            })
            ->with([
                "programCourses.course",
                "applicationForms" => fn ($q) => $q->where("active", true),
            ])
            ->orderBy("name")
            ->get();

        // Only programs that have at least one published/open course offering
        $programs = $programs->filter(function ($program) {
            return $program->programCourses->isNotEmpty();
        })->values();

        return view("programs.index", compact("programs"));
    }

    /**
     * Public brochure for a single program, looked up by code.
     */
    public function show(string $code): View
    {
        $program = Program::query()
            ->where("code", $code)
            ->where("active", true)
            ->with([
                "programCourses" => fn ($q) => $q->with("course")->orderBy("year_level")->orderBy("requirement"),
                "applicationForms" => fn ($q) => $q->where("active", true),
            ])
            ->firstOrFail();

        // Group courses by year_level
        $coursesByYear = $program->programCourses
            ->groupBy("year_level")
            ->sortKeys();

        // Compute total credits
        $totalCredits = $program->programCourses->sum(fn ($pc) => $pc->course->credit_hours ?? 0);

        return view("programs.show", compact("program", "coursesByYear", "totalCredits"));
    }
}
