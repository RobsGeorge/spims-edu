<?php

namespace App\Models;

use App\Enums\ProgramType;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Program extends Model
{
    use HasUlids;
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'type',
        'passing_threshold',
        'max_credits_per_semester',
        'max_courses_per_semester',
        'max_semesters_to_graduate',
        'elective_credits_required',
        'signatory_name',
        'signatory_title',
        'grading_scheme_id',
        'active',
    ];

    protected $casts = [
        'type' => ProgramType::class,
        'passing_threshold' => 'float',
        'max_credits_per_semester' => 'integer',
        'max_courses_per_semester' => 'integer',
        'max_semesters_to_graduate' => 'integer',
        'elective_credits_required' => 'integer',
        'active' => 'boolean',
    ];

    public function gradingScheme(): BelongsTo
    {
        return $this->belongsTo(GradingScheme::class);
    }

    public function programCourses(): HasMany
    {
        return $this->hasMany(ProgramCourse::class);
    }
}
