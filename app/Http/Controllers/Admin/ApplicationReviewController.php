<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Services\Admissions\ApplicationService;
use App\Support\AuthorizeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApplicationReviewController extends Controller
{
    public function index(Request $request, AuthorizeService $authorize): View
    {
        $authorize->authorize($request->user(), 'admissions.review');

        $queueStatuses = [
            ApplicationStatus::Submitted,
            ApplicationStatus::UnderReview,
            ApplicationStatus::Waitlisted,
        ];

        $statusFilter = $request->query('status');
        $resolvedStatus = null;
        if (is_string($statusFilter) && $statusFilter !== '') {
            $resolvedStatus = ApplicationStatus::tryFrom($statusFilter);
        }

        $query = Application::query()
            ->with(['applicant', 'program', 'reviewer'])
            ->latest('submitted_at');

        if ($resolvedStatus !== null) {
            $query->where('status', $resolvedStatus);
        } else {
            $query->whereIn('status', $queueStatuses);
        }

        return view('admin.applications.index', [
            'applications' => $query->paginate(20),
            'statusOptions' => ApplicationStatus::cases(),
            'currentStatus' => $resolvedStatus?->value,
        ]);
    }

    public function show(Request $request, Application $application, AuthorizeService $authorize, ApplicationService $service): View
    {
        $authorize->authorize($request->user(), 'admissions.review');

        $application->load(['applicant', 'program', 'form.fields', 'values.field', 'reviewer']);

        $decisions = [
            ApplicationStatus::Accepted,
            ApplicationStatus::Rejected,
            ApplicationStatus::Waitlisted,
        ];

        return view('admin.applications.show', [
            'application' => $application,
            'answers' => $service->displayAnswers($application),
            'statusLabel' => $application->status->label(),
            'decisions' => array_map(fn (ApplicationStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ], $decisions),
        ]);
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
