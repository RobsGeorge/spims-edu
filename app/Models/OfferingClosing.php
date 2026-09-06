<?php

namespace App\Models;

use App\Enums\OfferingClosingStatus;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferingClosing extends Model
{
    use HasUlids;

    protected $fillable = [
        'offering_id',
        'status',
        'grace_marks',
        'locked_at',
        'locked_by_id',
        'announced_at',
        'announced_by_id',
        'closed_at',
        'closed_by_id',
    ];

    protected $casts = [
        'status' => OfferingClosingStatus::class,
        'grace_marks' => 'array',
        'locked_at' => 'datetime',
        'announced_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function offering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class, 'offering_id');
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by_id');
    }

    public function announcedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'announced_by_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_id');
    }

    /**
     * Grace points for one student, for MIN_GRADE criteria evaluation only —
     * see OfferingClosingService::applyGraceMarks() for the design note.
     */
    public function graceFor(string $studentId): float
    {
        return (float) ($this->grace_marks[$studentId] ?? 0);
    }
}
