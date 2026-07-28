<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Enums\GradeStatus;
use App\Enums\GradeType;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Enrollment extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'student_id',
        'offering_id',
        'student_program_id',
        'status',
        'is_audit',
        'enrolled_at',
        'dropped_at',
        'grade_type',
        'final_percent',
        'final_letter',
        'final_gpa_points',
        'grade_status',
        'grade_locked_by_id',
        'grade_locked_at',
        'progress_percent',
    ];

    protected $casts = [
        'status' => EnrollmentStatus::class,
        'is_audit' => 'boolean',
        'enrolled_at' => 'datetime',
        'dropped_at' => 'datetime',
        'grade_type' => GradeType::class,
        'grade_status' => GradeStatus::class,
        'final_percent' => 'float',
        'final_gpa_points' => 'float',
        'grade_locked_at' => 'datetime',
        'progress_percent' => 'float',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function offering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class, 'offering_id');
    }

    public function studentProgram(): BelongsTo
    {
        return $this->belongsTo(StudentProgram::class);
    }
}
