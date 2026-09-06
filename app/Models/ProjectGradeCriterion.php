<?php

namespace App\Models;

use App\Enums\ProjectGradeLevel;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectGradeCriterion extends Model
{
    use HasUlids;

    protected $table = 'project_grade_criteria';

    protected $fillable = [
        'project_assessment_id',
        'name',
        'weight',
        'level',
    ];

    protected $casts = [
        'weight' => 'float',
        'level' => ProjectGradeLevel::class,
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(ProjectAssessment::class, 'project_assessment_id');
    }

    public function grades(): HasMany
    {
        return $this->hasMany(ProjectGrade::class, 'criterion_id');
    }
}
