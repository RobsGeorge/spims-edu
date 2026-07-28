<?php

namespace App\Models;

use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicRecord extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'student_id',
        'course_id',
        'enrollment_id',
        'letter_grade',
        'percent',
        'gpa_points',
        'credit_hours',
        'term',
        'is_passing',
        'completed_at',
    ];

    protected $casts = [
        'percent' => 'float',
        'gpa_points' => 'float',
        'credit_hours' => 'integer',
        'is_passing' => 'boolean',
        'completed_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
