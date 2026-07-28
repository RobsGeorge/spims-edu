<?php

namespace App\Models;

use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentQuestion extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'assessment_id',
        'question_id',
        'order',
        'points_override',
    ];

    protected $casts = [
        'order' => 'integer',
        'points_override' => 'float',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function points(): float
    {
        return (float) ($this->points_override ?? $this->question?->points ?? 0);
    }
}
