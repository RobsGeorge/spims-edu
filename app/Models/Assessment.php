<?php

namespace App\Models;

use App\Enums\AssessmentMode;
use App\Enums\ResultsVisibility;
use App\Enums\ScoringRule;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assessment extends Model
{
    use HasUlids;

    protected $fillable = [
        'offering_id',
        'content_item_id',
        'component_id',
        'mode',
        'title',
        'language',
        'time_limit_minutes',
        'opens_at',
        'closes_at',
        'attempts_allowed',
        'scoring_rule',
        'shuffle_questions',
        'shuffle_options',
        'draw_from_bank_id',
        'questions_to_draw',
        'results_visibility',
        'reveal_answers',
        'enforce_full_screen',
        'one_at_a_time',
        'no_backtrack',
        'log_focus_loss',
        'max_points',
        'item_weight',
        'released',
    ];

    protected $casts = [
        'mode' => AssessmentMode::class,
        'scoring_rule' => ScoringRule::class,
        'results_visibility' => ResultsVisibility::class,
        'opens_at' => 'datetime',
        'closes_at' => 'datetime',
        'time_limit_minutes' => 'integer',
        'attempts_allowed' => 'integer',
        'shuffle_questions' => 'boolean',
        'shuffle_options' => 'boolean',
        'questions_to_draw' => 'integer',
        'reveal_answers' => 'boolean',
        'enforce_full_screen' => 'boolean',
        'one_at_a_time' => 'boolean',
        'no_backtrack' => 'boolean',
        'log_focus_loss' => 'boolean',
        'max_points' => 'float',
        'item_weight' => 'float',
        'released' => 'boolean',
    ];

    public function offering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class, 'offering_id');
    }

    public function contentItem(): BelongsTo
    {
        return $this->belongsTo(ContentItem::class);
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(GradebookComponent::class, 'component_id');
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(QuestionBank::class, 'draw_from_bank_id');
    }

    public function assessmentQuestions(): HasMany
    {
        return $this->hasMany(AssessmentQuestion::class)->orderBy('order');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(AssessmentAttempt::class);
    }

    public function isOpen(): bool
    {
        $now = now();
        if ($this->opens_at && $now->lt($this->opens_at)) {
            return false;
        }
        if ($this->closes_at && $now->gt($this->closes_at)) {
            return false;
        }

        return true;
    }
}
