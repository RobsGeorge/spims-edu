<?php

namespace App\Models;

use App\Enums\ProjectAssessmentStatus;
use App\Enums\ProjectGradingMode;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectAssessment extends Model
{
    use HasUlids;

    protected $fillable = [
        'offering_id',
        'title',
        'component_id',
        'team_size_min',
        'team_size_max',
        'join_opens_at',
        'join_closes_at',
        'allow_leave_once',
        'grading_mode',
        'max_points',
        'status',
        'peer_opens_at',
        'peer_closes_at',
    ];

    protected $casts = [
        'team_size_min' => 'integer',
        'team_size_max' => 'integer',
        'join_opens_at' => 'datetime',
        'join_closes_at' => 'datetime',
        'allow_leave_once' => 'boolean',
        'grading_mode' => ProjectGradingMode::class,
        'max_points' => 'float',
        'status' => ProjectAssessmentStatus::class,
        'peer_opens_at' => 'datetime',
        'peer_closes_at' => 'datetime',
    ];

    public function offering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class, 'offering_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(GradebookComponent::class, 'component_id');
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function phases(): HasMany
    {
        return $this->hasMany(ProjectPhase::class)->orderBy('position');
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(ProjectGradeCriterion::class);
    }

    public function grades(): HasMany
    {
        return $this->hasMany(ProjectGrade::class);
    }

    public function changeRequests(): HasMany
    {
        return $this->hasMany(ProjectChangeRequest::class);
    }

    public function isJoinWindowOpen(): bool
    {
        $now = now();

        if ($this->join_opens_at !== null && $now->lt($this->join_opens_at)) {
            return false;
        }

        if ($this->join_closes_at !== null && $now->gt($this->join_closes_at)) {
            return false;
        }

        return true;
    }

    public function isPeerWindowOpen(): bool
    {
        $now = now();

        if ($this->peer_opens_at === null || $now->lt($this->peer_opens_at)) {
            return false;
        }

        if ($this->peer_closes_at !== null && $now->gt($this->peer_closes_at)) {
            return false;
        }

        return true;
    }
}
