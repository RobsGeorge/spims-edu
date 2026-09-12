<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ImportAccountClaimStatus;
use App\Http\Controllers\Controller;
use App\Models\ImportAccountClaim;
use App\Services\Import\ImportAccountClaimService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * L5 — screen 13, the active-student account-claim cohort.
 * See docs/legacy-data-import-plan.md §9 and §11.11.
 */
class ImportActivationController extends Controller
{
    public function index(Request $request): View
    {
        $tab = (string) $request->query('tab', 'all');

        $claims = ImportAccountClaim::query()
            ->with(['user', 'batch'])
            ->latest('created_at')
            ->get();

        return view('admin.imports.activation', [
            'tab' => $tab,
            'claims' => $claims,
            'stats' => [
                'eligible' => $claims->count(),
                'invited' => $claims->whereIn('status', [ImportAccountClaimStatus::Sent, ImportAccountClaimStatus::Claimed])->count(),
                'claimed' => $claims->where('status', ImportAccountClaimStatus::Claimed)->count(),
                'bounced' => $claims->where('status', ImportAccountClaimStatus::Bounced)->count(),
            ],
        ]);
    }

    public function send(Request $request, ImportAccountClaimService $claims): RedirectResponse
    {
        $data = $request->validate([
            'claim_ids' => ['required', 'array', 'min:1'],
            'claim_ids.*' => ['string', 'exists:import_account_claims,id'],
        ]);

        $selected = ImportAccountClaim::query()->whereIn('id', $data['claim_ids'])->get();
        $result = $claims->send($request->user(), $selected);

        return redirect()->route('admin.imports.activation')
            ->with('status', __('import.claims_sent', ['count' => $result['sent']]));
    }

    public function bounce(Request $request, ImportAccountClaim $claim, ImportAccountClaimService $claims): RedirectResponse
    {
        $claims->markBounced($request->user(), $claim);

        return redirect()->route('admin.imports.activation', ['tab' => 'bounced'])
            ->with('status', __('import.claim_marked_bounced'));
    }

    public function correctEmail(Request $request, ImportAccountClaim $claim, ImportAccountClaimService $claims): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($claim->user_id)],
        ]);

        $claims->correctEmailAndRequeue($request->user(), $claim, $data['email']);

        return redirect()->route('admin.imports.activation', ['tab' => 'not_invited'])
            ->with('status', __('import.claim_email_corrected'));
    }
}
