<?php

namespace App\Models;

use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentSubmission extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'assignment_id',
        'student_id',
        'text_body',
        'file_url',
        'submitted_at',
        'is_late',
        'raw_score',
        'final_score',
        'feedback',
        'graded_by_id',
        'graded_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'is_late' => 'boolean',
        'raw_score' => 'float',
        'final_score' => 'float',
        'graded_at' => 'datetime',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
