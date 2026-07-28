<?php

namespace App\Models;

use App\Enums\RequirementType;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramCourse extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'program_id',
        'course_id',
        'requirement',
        'year_level',
    ];

    protected $casts = [
        'requirement' => RequirementType::class,
        'year_level' => 'integer',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
