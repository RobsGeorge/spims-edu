<?php

namespace App\Models;

use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FeedbackSubmission extends Model
{
    use HasUlids;

    protected $fillable = [
        'survey_id',
        'submitted_at',
        'is_anonymous',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'is_anonymous' => 'boolean',
    ];

    /**
     * Identity is a sealed side table. Never eager-load it on student paths,
     * and never put student_id on this model.
     */
    protected $hidden = [
        'identity',
    ];

    public function survey(): BelongsTo
    {
        return $this->belongsTo(FeedbackSurvey::class, 'survey_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(FeedbackAnswer::class, 'submission_id');
    }

    public function identity(): HasOne
    {
        return $this->hasOne(FeedbackSubmissionIdentity::class, 'submission_id');
    }

    public function revealRequests(): HasMany
    {
        return $this->hasMany(FeedbackIdentityRevealRequest::class, 'submission_id');
    }
}
