<?php

namespace App\Http\Controllers;

use App\Models\AcademicRecord;
use App\Models\Credential;
use App\Services\Credentials\CredentialService;
use App\Services\Gradebook\GradebookService;
use App\Support\AuthorizeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TranscriptController extends Controller
{
    public function __invoke(Request $request, CredentialService $credentials, AuthorizeService $authorize): View
    {
        $user = $request->user();
        $data = $credentials->transcriptData($user);

        return view('credentials.transcript', [
            'student' => $user,
            'records' => $data['records'],
            'gpa' => $data['gpa'],
            'priorStudy' => $data['prior_study'],
            'legacySummaries' => $data['legacy_summaries'],
            // L4 — the "promote to transfer credit" button on a Prior study row is
            // shown only to a viewer who could commit an import in the first place;
            // see docs/legacy-data-import-plan.md §7, D7.
            'canPromote' => $authorize->allows($request->user(), 'import.commit'),
            'credentials' => Credential::query()
                ->where('student_id', $user->id)
                ->whereNull('revoked_at')
                ->latest('issued_at')
                ->get(),
        ]);
    }

    /**
     * D7 — registrar-approved transfer credit, one record at a time. See
     * GradebookService::promoteToTransferCredit().
     */
    public function promote(Request $request, AcademicRecord $record, GradebookService $gradebook): RedirectResponse
    {
        $gradebook->promoteToTransferCredit($request->user(), $record);

        return back()->with('status', __('credentials.prior_study_promoted'));
    }
}
