<?php

namespace App\Models;

use App\Enums\StudentProgramStatus;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentProgram extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'student_id',
        'program_id',
        'status',
        'enrolled_at',
        'completed_at',
        'cached_gpa',
    ];

    protected $casts = [
        'status' => StudentProgramStatus::class,
        'enrolled_at' => 'datetime',
        'completed_at' => 'datetime',
        'cached_gpa' => 'float',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function fulfillments(): HasMany
    {
        return $this->hasMany(ProgramRequirementFulfillment::class);
    }
}
