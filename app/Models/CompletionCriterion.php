<?php

namespace App\Models;

use App\Enums\CompletionCriterionKind;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompletionCriterion extends Model
{
    use HasUlids;

    protected $fillable = [
        'course_id',
        'offering_id',
        'kind',
        'threshold',
        'content_item_id',
        'is_required',
    ];

    protected $casts = [
        'kind' => CompletionCriterionKind::class,
        'threshold' => 'float',
        'is_required' => 'boolean',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function offering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class, 'offering_id');
    }

    public function contentItem(): BelongsTo
    {
        return $this->belongsTo(ContentItem::class);
    }

    public function isOfferingScoped(): bool
    {
        return $this->offering_id !== null;
    }
}
