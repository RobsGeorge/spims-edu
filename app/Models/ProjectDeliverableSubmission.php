<?php

namespace App\Models;

use App\Enums\ProjectReviewStatus;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectDeliverableSubmission extends Model
{
    use HasUlids;

    protected $fillable = [
        'project_id',
        'deliverable_id',
        'body',
        'link',
        'reviewed_at',
        'review_status',
        'reviewer_id',
        'late',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'review_status' => ProjectReviewStatus::class,
        'late' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function deliverable(): BelongsTo
    {
        return $this->belongsTo(ProjectDeliverable::class, 'deliverable_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProjectSubmissionFile::class, 'submission_id');
    }
}
