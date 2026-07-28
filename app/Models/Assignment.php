<?php

namespace App\Models;

use App\Enums\SubmissionType;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assignment extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'content_item_id',
        'component_id',
        'instructions',
        'submission_type',
        'allowed_file_types',
        'max_points',
        'item_weight',
        'released',
        'due_date',
        'late_penalty_override',
    ];

    protected $casts = [
        'submission_type' => SubmissionType::class,
        'allowed_file_types' => 'array',
        'max_points' => 'float',
        'item_weight' => 'float',
        'released' => 'boolean',
        'due_date' => 'datetime',
        'late_penalty_override' => 'float',
    ];

    public function contentItem(): BelongsTo
    {
        return $this->belongsTo(ContentItem::class);
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(GradebookComponent::class, 'component_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }
}
