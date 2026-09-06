<?php

namespace App\Models;

use App\Enums\AttemptStatus;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentAttempt extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'assessment_id',
        'student_id',
        'attempt_no',
        'started_at',
        'due_at',
        'submitted_at',
        'status',
        'total_score',
        'focus_loss_count',
        'question_ids',
        'exam_snapshot',
        'proctor_warnings',
        'terminated_for_cheating',
        'terminated_at',
        'terminated_by_id',
        'termination_cleared_at',
        'termination_cleared_by_id',
    ];

    protected $casts = [
        'status' => AttemptStatus::class,
        'attempt_no' => 'integer',
        'started_at' => 'datetime',
        'due_at' => 'datetime',
        'submitted_at' => 'datetime',
        'total_score' => 'float',
        'focus_loss_count' => 'integer',
        'question_ids' => 'array',
        'exam_snapshot' => 'array',
        'proctor_warnings' => 'integer',
        'terminated_for_cheating' => 'boolean',
        'terminated_at' => 'datetime',
        'termination_cleared_at' => 'datetime',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AttemptAnswer::class, 'attempt_id');
    }

    public function proctorEvents(): HasMany
    {
        return $this->hasMany(ProctorEvent::class, 'attempt_id');
    }

    public function terminatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'terminated_by_id');
    }

    public function isExpired(): bool
    {
        return $this->status === AttemptStatus::InProgress && now()->gte($this->due_at);
    }
}
