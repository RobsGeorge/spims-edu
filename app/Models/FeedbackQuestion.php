<?php

namespace App\Models;

use App\Enums\FeedbackQuestionKind;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeedbackQuestion extends Model
{
    use HasUlids;

    protected $fillable = [
        'survey_id',
        'prompt',
        'kind',
        'options',
        'position',
        'required',
    ];

    protected $casts = [
        'kind' => FeedbackQuestionKind::class,
        'options' => 'array',
        'position' => 'integer',
        'required' => 'boolean',
    ];

    public function survey(): BelongsTo
    {
        return $this->belongsTo(FeedbackSurvey::class, 'survey_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(FeedbackAnswer::class, 'question_id');
    }
}
