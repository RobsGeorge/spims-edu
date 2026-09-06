<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CompletionOutcome;
use App\Exceptions\AuthorizationException;
use App\Http\Controllers\Controller;
use App\Models\CompletionResult;
use App\Models\CourseOffering;
use App\Models\User;
use App\Services\Completion\CompletionService;
use App\Support\AuthorizeService;
use App\Support\Scope\ResourceScopeResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CompletionController extends Controller
{
    public function show(
        Request $request,
        CourseOffering $offering,
        CompletionService $completion,
        AuthorizeService $authorize,
        ResourceScopeResolver $scope,
    ): JsonResponse {
        $user = $request->user();

        if ($this->actorSeesCohort($user, $offering, $authorize, $scope)) {
            return response()->json([
                'data' => $this->cohortPayload($completion->cohort($user, $offering)),
            ]);
        }

        try {
            $result = $completion->own($user, $offering);
        } catch (AuthorizationException) {
            abort(404);
        }

        return response()->json(['data' => $this->ownPayload($result)]);
    }

    public function evaluate(Request $request, CourseOffering $offering, CompletionService $completion): JsonResponse
    {
        $results = $completion->evaluate($request->user(), $offering);

        return response()->json(['data' => $this->cohortPayload($results)]);
    }

    /**
     * Staff/admin with `completion.view` on this offering see the cohort.
     * Students also hold an unscoped O on that key (STUDENT is not a scoped
     * role), so a successful authorize() is not enough — they must be offering
     * staff, or hold `completion.configure` (academic/super admin), which
     * students do not.
     */
    private function actorSeesCohort(
        User $user,
        CourseOffering $offering,
        AuthorizeService $authorize,
        ResourceScopeResolver $scope,
    ): bool {
        try {
            $authorize->authorize($user, 'completion.view', $offering);
        } catch (AuthorizationException) {
            return false;
        }

        if ($scope->scopedTo($user, $offering)) {
            return true;
        }

        try {
            $authorize->authorize($user, 'completion.configure', $offering);

            return true;
        } catch (AuthorizationException) {
            return false;
        }
    }

    /** @return array<string, mixed> */
    private function ownPayload(?CompletionResult $result): array
    {
        if ($result === null) {
            return [
                'outcome' => CompletionOutcome::Pending->value,
                'met_criteria' => [],
                'evaluated_at' => null,
            ];
        }

        return [
            'outcome' => $result->outcome->value,
            'met_criteria' => $result->met_criteria ?? [],
            'evaluated_at' => $result->evaluated_at?->toIso8601String(),
        ];
    }

    /**
     * @param  Collection<int, CompletionResult>  $results
     * @return array<int, array<string, mixed>>
     */
    private function cohortPayload(Collection $results): array
    {
        return $results->map(fn (CompletionResult $result) => [
            'student_id' => $result->student_id,
            'outcome' => $result->outcome->value,
            'met_criteria' => $result->met_criteria ?? [],
            'evaluated_at' => $result->evaluated_at?->toIso8601String(),
        ])->values()->all();
    }
}
