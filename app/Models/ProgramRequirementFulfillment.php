<?php

namespace App\Models;

use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramRequirementFulfillment extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'student_program_id',
        'program_course_id',
        'academic_record_id',
        'applied_at',
    ];

    protected $casts = [
        'applied_at' => 'datetime',
    ];

    public function studentProgram(): BelongsTo
    {
        return $this->belongsTo(StudentProgram::class);
    }

    public function programCourse(): BelongsTo
    {
        return $this->belongsTo(ProgramCourse::class);
    }

    public function academicRecord(): BelongsTo
    {
        return $this->belongsTo(AcademicRecord::class);
    }
}
