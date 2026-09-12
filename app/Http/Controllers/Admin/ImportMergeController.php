<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ImportMergeCandidateStatus;
use App\Http\Controllers\Controller;
use App\Models\ImportMergeCandidate;
use App\Models\ImportRow;
use App\Services\Import\ImportBatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Screen 12 — the identity review queue (rung 6 of the matching ladder). See
 * docs/legacy-data-import-plan.md §11.10.
 */
class ImportMergeController extends Controller
{
    public function index(): View
    {
        $candidates = ImportMergeCandidate::query()
            ->where('status', ImportMergeCandidateStatus::Pending)
            ->with(['source', 'batch', 'candidateUser'])
            ->orderBy('created_at')
            ->paginate(20);

        // The incoming row's normalized fields (first/last/email/DOB) make a much more
        // useful compare panel than the raw payload_preview alone — fetch them per
        // candidate. The list is a human worklist (tens, not thousands), so one query
        // per row is an acceptable cost here.
        $incoming = [];
        foreach ($candidates as $candidate) {
            $row = ImportRow::query()
                ->where('batch_id', $candidate->batch_id)
                ->where('natural_key', $candidate->legacy_id)
                ->first();
            $incoming[$candidate->id] = $row?->normalized ?? [];
        }

        return view('admin.imports.merges', [
            'candidates' => $candidates,
            'incoming' => $incoming,
            'pendingCount' => ImportMergeCandidate::query()->where('status', ImportMergeCandidateStatus::Pending)->count(),
        ]);
    }

    public function resolve(Request $request, ImportMergeCandidate $candidate, ImportBatchService $imports): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:merge,reject,skip'],
        ]);

        try {
            $imports->resolveMergeCandidate($request->user(), $candidate, $data['decision']);
        } catch (\RuntimeException $e) {
            return redirect()->route('admin.imports.merges')->with('error', $e->getMessage());
        }

        $message = match ($data['decision']) {
            'merge' => __('import.merge_resolved_merge'),
            'reject' => __('import.merge_resolved_reject'),
            default => __('import.merge_resolved_skip'),
        };

        return redirect()->route('admin.imports.merges')->with('status', $message);
    }
}
