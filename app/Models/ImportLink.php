<?php

namespace App\Models;

use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The permanent idempotency map — outlives the batches that wrote it. A re-run of the
 * same source data finds its row here on rung 1 of the matching ladder and links
 * instead of duplicating. See docs/legacy-data-import-plan.md §6.
 */
class ImportLink extends Model
{
    use HasUlids;

    protected $fillable = [
        'source_id',
        'entity_type',
        'legacy_id',
        'target_type',
        'target_id',
        'first_batch_id',
        'last_batch_id',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(ImportSource::class, 'source_id');
    }

    public function firstBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'first_batch_id');
    }

    public function lastBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'last_batch_id');
    }
}
