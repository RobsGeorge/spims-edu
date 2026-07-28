<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Services\Admissions\ApplicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApplicationReviewController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $query = Application::query()
            ->with(['applicant', 'program', 'reviewer'])
            ->whereIn('status', [
                ApplicationStatus::Submitted,
                ApplicationStatus::UnderReview,
                ApplicationStatus::Waitlisted,
            ])
            ->latest('submitted_at');

        if (! $user->isSuperAdmin() && ! $user->hasRole(\App\Enums\RoleType::AdministrativeAdmin)) {
            $query->where('reviewer_id', $user->id);
        }

        return view('admin.applications.index', [
            'applications' => $query->paginate(20),
        ]);
    }

    public function show(Application $application): View
    {
        $application->load(['applicant', 'program', 'form.fields', 'values.field', 'reviewer']);

        return view('admin.applications.show', compact('application'));
    }

    public function decide(Request $request, Application $application, ApplicationService $service): RedirectResponse
    {
        $data = $request->validate([
            'decision' => 'required|in:ACCEPTED,REJECTED,WAITLISTED',
            'decision_note' => 'nullable|string|max:2000',
        ]);

        $service->decide(
            $request->user(),
            $application,
            ApplicationStatus::from($data['decision']),
            $data['decision_note'] ?? null
        );

        return redirect()->route('admin.applications.index')->with('status', __('admissions.decision_saved'));
    }
}
