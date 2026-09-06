<?php

namespace App\Models;

use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sealed identity for an otherwise anonymous submission. student_id is hidden
 * from array/JSON serialization; the only legitimate read is
 * FeedbackReportService after an APPROVED reveal request.
 */
class FeedbackSubmissionIdentity extends Model
{
    use HasUlids;

    protected $fillable = [
        'submission_id',
        'student_id',
    ];

    protected $hidden = [
        'student_id',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(FeedbackSubmission::class, 'submission_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
