<?php

namespace App\Models;

use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectGrade extends Model
{
    use HasUlids;

    protected $fillable = [
        'project_assessment_id',
        'project_id',
        'student_id',
        'criterion_id',
        'score',
        'announced_at',
    ];

    protected $casts = [
        'score' => 'float',
        'announced_at' => 'datetime',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(ProjectAssessment::class, 'project_assessment_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(ProjectGradeCriterion::class, 'criterion_id');
    }
}
