<?php

namespace App\Models;

use App\Enums\FeedbackSurveyStatus;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeedbackSurvey extends Model
{
    use HasUlids;

    protected $fillable = [
        'offering_id',
        'title',
        'status',
        'anonymous_default',
        'opens_at',
        'closes_at',
        'created_by',
    ];

    protected $casts = [
        'status' => FeedbackSurveyStatus::class,
        'anonymous_default' => 'boolean',
        'opens_at' => 'datetime',
        'closes_at' => 'datetime',
    ];

    public function offering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class, 'offering_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(FeedbackQuestion::class, 'survey_id')->orderBy('position');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(FeedbackSubmission::class, 'survey_id');
    }

    public function isDraft(): bool
    {
        return $this->status === FeedbackSurveyStatus::Draft;
    }

    public function isPublished(): bool
    {
        return $this->status === FeedbackSurveyStatus::Published;
    }

    public function isClosed(): bool
    {
        return $this->status === FeedbackSurveyStatus::Closed;
    }

    public function isWithinWindow(): bool
    {
        $now = now();

        if ($this->opens_at !== null && $this->opens_at->isFuture()) {
            return false;
        }

        if ($this->closes_at !== null && $this->closes_at->isPast()) {
            return false;
        }

        return true;
    }

    public function isAcceptingSubmissions(): bool
    {
        return $this->isPublished() && $this->isWithinWindow();
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            FeedbackSurveyStatus::Draft => __('feedback.status_draft'),
            FeedbackSurveyStatus::Published => __('feedback.status_published'),
            FeedbackSurveyStatus::Closed => __('feedback.status_closed'),
        };
    }
}
