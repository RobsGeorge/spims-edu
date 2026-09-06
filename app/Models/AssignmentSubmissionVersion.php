<?php

namespace App\Models;

use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentSubmissionVersion extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'submission_id',
        'attempt_no',
        'text_body',
        'file_url',
        'submitted_at',
        'is_late',
        'raw_score',
        'final_score',
        'feedback',
        'graded_by_id',
        'graded_at',
        'archived_at',
    ];

    protected $casts = [
        'attempt_no' => 'integer',
        'is_late' => 'boolean',
        'raw_score' => 'float',
        'final_score' => 'float',
        'submitted_at' => 'datetime',
        'graded_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(AssignmentSubmission::class, 'submission_id');
    }

    public function gradedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by_id');
    }
}
