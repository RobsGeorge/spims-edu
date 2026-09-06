<?php

namespace App\Models;

use App\Enums\ProjectChangeRequestKind;
use App\Enums\ProjectChangeRequestStatus;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectChangeRequest extends Model
{
    use HasUlids;

    protected $fillable = [
        'project_assessment_id',
        'student_id',
        'kind',
        'reason',
        'status',
        'target_project_id',
        'meta',
        'decided_by_id',
        'decided_at',
        'decision_note',
    ];

    protected $casts = [
        'kind' => ProjectChangeRequestKind::class,
        'status' => ProjectChangeRequestStatus::class,
        'meta' => 'array',
        'decided_at' => 'datetime',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(ProjectAssessment::class, 'project_assessment_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function targetProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'target_project_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_id');
    }
}
