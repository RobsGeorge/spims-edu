<?php

namespace App\Models;

use App\Enums\ImportBatchStatus;
use App\Enums\ImportEntityType;
use App\Enums\ImportPopulation;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    use HasUlids;

    protected $fillable = [
        'source_id',
        'entity_type',
        'population',
        'status',
        'mapping_profile_id',
        'file_name',
        'file_path',
        'file_hash',
        'sheet_name',
        'header_row',
        'row_count',
        'error_count',
        'warning_count',
        'profile',
        'mapping',
        'control_totals',
        'dry_run_report',
        'commit_progress',
        'sealed_at',
        'created_by_id',
        'committed_by_id',
        'rolled_back_by_id',
        'mapped_at',
        'validated_at',
        'dry_run_at',
        'committed_at',
        'rolled_back_at',
    ];

    protected $casts = [
        'entity_type' => ImportEntityType::class,
        'population' => ImportPopulation::class,
        'status' => ImportBatchStatus::class,
        'header_row' => 'integer',
        'row_count' => 'integer',
        'error_count' => 'integer',
        'warning_count' => 'integer',
        'profile' => 'array',
        'mapping' => 'array',
        'control_totals' => 'array',
        'dry_run_report' => 'array',
        'commit_progress' => 'array',
        'sealed_at' => 'datetime',
        'mapped_at' => 'datetime',
        'validated_at' => 'datetime',
        'dry_run_at' => 'datetime',
        'committed_at' => 'datetime',
        'rolled_back_at' => 'datetime',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(ImportSource::class, 'source_id');
    }

    public function mappingProfile(): BelongsTo
    {
        return $this->belongsTo(ImportMappingProfile::class, 'mapping_profile_id');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class, 'batch_id');
    }

    /**
     * L5 — the account-claim invitation rows queued when this batch committed.
     * See docs/legacy-data-import-plan.md §9.
     */
    public function accountClaims(): HasMany
    {
        return $this->hasMany(ImportAccountClaim::class, 'batch_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function committedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'committed_by_id');
    }

    public function rolledBackBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rolled_back_by_id');
    }

    public function isSealed(): bool
    {
        return $this->sealed_at !== null && $this->sealed_at->isPast();
    }
}
