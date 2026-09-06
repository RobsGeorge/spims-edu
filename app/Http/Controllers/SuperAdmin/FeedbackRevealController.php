<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\FeedbackIdentityRevealRequest;
use App\Services\Feedback\FeedbackIdentityRevealService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeedbackRevealController extends Controller
{
    public function index(): View
    {
        $requests = FeedbackIdentityRevealRequest::query()
            ->with(['submission.survey', 'requester'])
            ->orderByDesc('created_at')
            ->get();

        return view('superadmin.feedback-reveals', [
            'requests' => $requests,
        ]);
    }

    public function decide(
        Request $request,
        FeedbackIdentityRevealRequest $reveal,
        FeedbackIdentityRevealService $reveals,
    ): RedirectResponse {
        $data = $request->validate([
            'approve' => 'required|boolean',
            'reason' => 'nullable|string|max:2000',
        ]);

        $approve = $request->boolean('approve');

        $reveals->decide(
            $request->user(),
            $reveal,
            $approve,
            $data['reason'] ?? null,
        );

        return back()->with('status', $approve
            ? __('staff.surveys.reveal_approved')
            : __('staff.surveys.reveal_denied'));
    }
}
