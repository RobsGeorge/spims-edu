<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasUlids;

    protected $fillable = [
        'project_assessment_id',
        'name',
        'status',
        'workspace_url',
    ];

    protected $casts = [
        'status' => ProjectStatus::class,
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(ProjectAssessment::class, 'project_assessment_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(ProjectMembership::class);
    }

    public function activeMemberships(): HasMany
    {
        return $this->hasMany(ProjectMembership::class)->whereNull('left_at');
    }

    public function membershipEvents(): HasMany
    {
        return $this->hasMany(ProjectMembershipEvent::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(ProjectDeliverableSubmission::class);
    }

    public function peerEvaluations(): HasMany
    {
        return $this->hasMany(ProjectPeerEvaluation::class);
    }

    public function grades(): HasMany
    {
        return $this->hasMany(ProjectGrade::class);
    }
}
