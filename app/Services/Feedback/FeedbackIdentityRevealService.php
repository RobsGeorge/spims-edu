<?php

namespace App\Services\Feedback;

use App\Enums\FeedbackIdentityRevealStatus;
use App\Models\FeedbackIdentityRevealRequest;
use App\Models\FeedbackSubmission;
use App\Models\User;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class FeedbackIdentityRevealService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
    ) {}

    public function request(User $actor, FeedbackSubmission $submission, ?string $reason = null): FeedbackIdentityRevealRequest
    {
        $submission->loadMissing('survey');
        $this->authorize->authorize($actor, 'feedback.identity.request', $submission->survey);

        $pending = FeedbackIdentityRevealRequest::query()
            ->where('submission_id', $submission->id)
            ->where('status', FeedbackIdentityRevealStatus::Pending)
            ->exists();

        if ($pending) {
            throw new ConflictHttpException(__('feedback.reveal_already_pending'));
        }

        return $this->audit->withAudit($actor, 'feedback.identity.request', function () use ($actor, $submission, $reason) {
            return FeedbackIdentityRevealRequest::query()->create([
                'submission_id' => $submission->id,
                'requester_id' => $actor->id,
                'status' => FeedbackIdentityRevealStatus::Pending,
                'reason' => $reason,
            ]);
        }, FeedbackIdentityRevealRequest::class);
    }

    public function decide(User $actor, FeedbackIdentityRevealRequest $reveal, bool $approve, ?string $reason = null): FeedbackIdentityRevealRequest
    {
        $this->authorize->authorize($actor, 'feedback.identity.reveal');

        if (! $reveal->isPending()) {
            throw ValidationException::withMessages([
                'status' => [__('feedback.reveal_already_decided')],
            ]);
        }

        return $this->audit->withAudit($actor, 'feedback.identity.reveal', function () use ($actor, $reveal, $approve, $reason) {
            $reveal->status = $approve
                ? FeedbackIdentityRevealStatus::Approved
                : FeedbackIdentityRevealStatus::Denied;
            $reveal->decided_by_id = $actor->id;
            $reveal->decided_at = now();
            if ($reason !== null) {
                $reveal->reason = $reason;
            }
            $reveal->save();

            return $reveal->fresh();
        }, FeedbackIdentityRevealRequest::class);
    }
}
