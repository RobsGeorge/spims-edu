<?php

namespace App\Models;

use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The attested legacy GPA figure (D3) — Populi's own number, on its own scale, as of a
 * declared date. Rendered beside the live SPIMS `cached_gpa`, never averaged into it.
 * See docs/legacy-data-import-plan.md §4.1, §7.
 */
class LegacyAcademicSummary extends Model
{
    use HasUlids;

    protected $fillable = [
        'student_id',
        'source_id',
        'program_id',
        'student_program_id',
        'scope',
        'gpa',
        'gpa_scale',
        'credits_attempted',
        'credits_earned',
        'standing',
        'honors',
        'as_of',
        'attested_by_id',
        'attested_at',
    ];

    protected $casts = [
        'gpa' => 'float',
        'gpa_scale' => 'float',
        'credits_attempted' => 'integer',
        'credits_earned' => 'integer',
        'as_of' => 'date',
        'attested_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(ImportSource::class, 'source_id');
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function studentProgram(): BelongsTo
    {
        return $this->belongsTo(StudentProgram::class);
    }

    public function attestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attested_by_id');
    }
}
