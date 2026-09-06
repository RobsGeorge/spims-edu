<?php

namespace App\Services\Projects;

use App\Exceptions\AuthorizationException;
use App\Exceptions\ConflictException;
use App\Models\Project;
use App\Models\ProjectAssessment;
use App\Models\ProjectPeerEvaluation;
use App\Models\User;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Peer evaluation is informational. This service never reads or writes
 * `project_grades` (or any other grade table). Announce ignores these rows.
 */
class PeerEvaluationService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
        private readonly ProjectTeamService $teams,
    ) {}

    public function open(User $actor, ProjectAssessment $assessment): ProjectAssessment
    {
        $this->authorize->authorize($actor, 'projects.manage', $assessment);

        return $this->audit->withAudit($actor, 'projects.peer_open', function () use ($assessment) {
            if ($assessment->peer_opens_at === null) {
                $assessment->peer_opens_at = now();
            }
            $assessment->save();

            return $assessment->fresh();
        }, 'ProjectAssessment');
    }

    public function close(User $actor, ProjectAssessment $assessment): ProjectAssessment
    {
        $this->authorize->authorize($actor, 'projects.manage', $assessment);

        return $this->audit->withAudit($actor, 'projects.peer_close', function () use ($assessment) {
            $assessment->peer_closes_at = now();
            $assessment->save();

            return $assessment->fresh();
        }, 'ProjectAssessment');
    }

    /**
     * @param  array{ratee_id: string, score: float|int, comment?: ?string}  $data
     */
    public function submit(User $rater, Project $project, array $data): ProjectPeerEvaluation
    {
        $this->authorize->authorize($rater, 'projects.peer_eval');
        $project->load('assessment');
        $this->teams->assertEnrolled($rater, $project->assessment);

        if ($this->teams->activeMembership($rater, $project) === null) {
            throw new AuthorizationException(__('auth.forbidden'));
        }

        if (! $project->assessment->isPeerWindowOpen()) {
            if ($project->assessment->peer_opens_at === null || now()->lt($project->assessment->peer_opens_at)) {
                throw new ConflictException(__('projects.peer_not_open'));
            }

            throw new ConflictException(__('projects.peer_closed'));
        }

        $rateeId = (string) $data['ratee_id'];
        if ($rateeId === $rater->id) {
            throw ValidationException::withMessages([
                'ratee_id' => [__('projects.cannot_rate_self')],
            ]);
        }

        $ratee = User::query()->find($rateeId);
        if ($ratee === null || $this->teams->activeMembership($ratee, $project) === null) {
            throw ValidationException::withMessages([
                'ratee_id' => [__('projects.cannot_rate_outside_team')],
            ]);
        }

        $already = ProjectPeerEvaluation::query()
            ->where('project_id', $project->id)
            ->where('rater_id', $rater->id)
            ->where('ratee_id', $rateeId)
            ->exists();

        if ($already) {
            throw new ConflictException(__('projects.already_submitted_peer'));
        }

        return $this->audit->withAudit($rater, 'projects.peer_submit', function () use ($rater, $project, $data, $rateeId) {
            return ProjectPeerEvaluation::query()->create([
                'project_id' => $project->id,
                'rater_id' => $rater->id,
                'ratee_id' => $rateeId,
                'score' => $data['score'],
                'comment' => $data['comment'] ?? null,
                'submitted_at' => now(),
            ]);
        }, 'ProjectPeerEvaluation');
    }

    /**
     * @return array<int, array{id: string, first_name: ?string, last_name: ?string}>
     */
    public function pending(User $rater, Project $project): array
    {
        $this->authorize->authorize($rater, 'projects.peer_eval');
        if ($this->teams->activeMembership($rater, $project) === null) {
            throw new AuthorizationException(__('auth.forbidden'));
        }

        $teammates = $project->activeMemberships()->with('student')->get()
            ->pluck('student')
            ->filter()
            ->reject(fn (User $u) => $u->id === $rater->id);

        $done = ProjectPeerEvaluation::query()
            ->where('project_id', $project->id)
            ->where('rater_id', $rater->id)
            ->pluck('ratee_id')
            ->all();

        return $teammates
            ->reject(fn (User $u) => in_array($u->id, $done, true))
            ->map(fn (User $u) => [
                'id' => $u->id,
                'first_name' => $u->first_name,
                'last_name' => $u->last_name,
            ])
            ->values()
            ->all();
    }

    /**
     * Anonymous aggregates grouped by rater team. No rater_id is returned.
     *
     * @return array<int, array<string, mixed>>
     */
    public function aggregatesForStaff(User $actor, ProjectAssessment $assessment): array
    {
        $this->authorize->authorize($actor, 'projects.view', $assessment);

        $rows = ProjectPeerEvaluation::query()
            ->whereIn('project_id', $assessment->teams()->pluck('id'))
            ->get()
            ->groupBy('project_id');

        $out = [];
        foreach ($rows as $projectId => $evals) {
            /** @var Collection<int, ProjectPeerEvaluation> $evals */
            $out[] = [
                'project_id' => $projectId,
                'count' => $evals->count(),
                'average_score' => round((float) $evals->avg('score'), 2),
                'scores' => $evals->pluck('score')->map(fn ($s) => (float) $s)->values()->all(),
                'comments' => $evals->pluck('comment')->filter()->shuffle()->values()->all(),
            ];
        }

        return $out;
    }
}
