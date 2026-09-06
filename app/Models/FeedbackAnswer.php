<?php

namespace App\Models;

use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedbackAnswer extends Model
{
    use HasUlids;

    protected $fillable = [
        'submission_id',
        'question_id',
        'value',
    ];

    protected $casts = [
        'value' => 'array',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(FeedbackSubmission::class, 'submission_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(FeedbackQuestion::class, 'question_id');
    }
}
