<?php

namespace App\Models;

use App\Enums\FeedbackIdentityRevealStatus;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedbackIdentityRevealRequest extends Model
{
    use HasUlids;

    protected $fillable = [
        'submission_id',
        'requester_id',
        'status',
        'decided_by_id',
        'decided_at',
        'reason',
    ];

    protected $casts = [
        'status' => FeedbackIdentityRevealStatus::class,
        'decided_at' => 'datetime',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(FeedbackSubmission::class, 'submission_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_id');
    }

    public function isApproved(): bool
    {
        return $this->status === FeedbackIdentityRevealStatus::Approved;
    }

    public function isPending(): bool
    {
        return $this->status === FeedbackIdentityRevealStatus::Pending;
    }
}
