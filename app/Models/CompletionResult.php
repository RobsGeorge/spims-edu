<?php

namespace App\Models;

use App\Enums\CompletionOutcome;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompletionResult extends Model
{
    use HasUlids;

    protected $fillable = [
        'offering_id',
        'student_id',
        'met_criteria',
        'outcome',
        'evaluated_at',
    ];

    protected $casts = [
        'met_criteria' => 'array',
        'outcome' => CompletionOutcome::class,
        'evaluated_at' => 'datetime',
    ];

    public function offering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class, 'offering_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
