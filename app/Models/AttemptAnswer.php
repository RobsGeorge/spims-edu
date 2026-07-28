<?php

namespace App\Models;

use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttemptAnswer extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'attempt_id',
        'question_id',
        'response',
        'auto_score',
        'ai_suggested_score',
        'ai_rationale',
        'final_score',
        'feedback',
        'graded_by_id',
        'graded_at',
    ];

    protected $casts = [
        'response' => 'array',
        'auto_score' => 'float',
        'ai_suggested_score' => 'float',
        'final_score' => 'float',
        'graded_at' => 'datetime',
    ];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(AssessmentAttempt::class, 'attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
