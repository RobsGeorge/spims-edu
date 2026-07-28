<?php

namespace App\Models;

use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Course extends Model
{
    use HasUlids;
    use SoftDeletes;

    protected $fillable = [
        'code',
        'title',
        'credit_hours',
        'default_price_usd',
        'default_price_egp',
        'is_free',
        'is_standalone',
        'passing_threshold',
        'assessment_template_id',
        'active',
    ];

    protected $casts = [
        'credit_hours' => 'integer',
        'default_price_usd' => 'integer',
        'default_price_egp' => 'integer',
        'is_free' => 'boolean',
        'is_standalone' => 'boolean',
        'passing_threshold' => 'float',
        'active' => 'boolean',
    ];

    public function assessmentTemplate(): BelongsTo
    {
        return $this->belongsTo(AssessmentTemplate::class);
    }

    public function prerequisites(): BelongsToMany
    {
        return $this->belongsToMany(
            Course::class,
            'course_prerequisites',
            'course_id',
            'prerequisite_id'
        )->withPivot('id');
    }

    public function interestFlags(): HasMany
    {
        return $this->hasMany(CourseInterestFlag::class);
    }

    public function programCourses(): HasMany
    {
        return $this->hasMany(ProgramCourse::class);
    }
}
