<?php

namespace App\Models;

use App\Enums\ImportRowAction;
use App\Enums\ImportRowStatus;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportRow extends Model
{
    use HasUlids;

    protected $fillable = [
        'batch_id',
        'row_number',
        'natural_key',
        'payload',
        'normalized',
        'before_snapshot',
        'action',
        'status',
        'messages',
        'target_type',
        'target_id',
    ];

    protected $casts = [
        'row_number' => 'integer',
        'payload' => 'array',
        'normalized' => 'array',
        'before_snapshot' => 'array',
        'action' => ImportRowAction::class,
        'status' => ImportRowStatus::class,
        'messages' => 'array',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'batch_id');
    }

    public function hasErrors(): bool
    {
        return collect($this->messages ?? [])->contains(fn (array $m) => ($m['level'] ?? null) === 'error');
    }

    public function hasWarnings(): bool
    {
        return collect($this->messages ?? [])->contains(fn (array $m) => ($m['level'] ?? null) === 'warning');
    }
}
