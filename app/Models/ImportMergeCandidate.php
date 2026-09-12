<?php

namespace App\Models;

use App\Enums\ImportMergeCandidateStatus;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rung 6 of the matching ladder — a row that reached the end of the ladder without a
 * deterministic match. `candidate_user_id` is a best guess only (name similarity or a
 * normalised-name match without DOB) and may be null when no plausible candidate was
 * found at all. See docs/legacy-data-import-plan.md §4.1, §6, §11.10.
 */
class ImportMergeCandidate extends Model
{
    use HasUlids;

    protected $fillable = [
        'source_id',
        'legacy_id',
        'batch_id',
        'candidate_user_id',
        'score',
        'matched_on',
        'payload_preview',
        'status',
        'resolved_by_id',
        'resolved_at',
    ];

    protected $casts = [
        'score' => 'float',
        'matched_on' => 'array',
        'payload_preview' => 'array',
        'status' => ImportMergeCandidateStatus::class,
        'resolved_at' => 'datetime',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(ImportSource::class, 'source_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'batch_id');
    }

    public function candidateUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'candidate_user_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_id');
    }
}
