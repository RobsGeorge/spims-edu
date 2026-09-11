<?php

namespace App\Models;

use App\Enums\ImportEntityType;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportMappingProfile extends Model
{
    use HasUlids;

    protected $fillable = [
        'source_id',
        'entity_type',
        'name',
        'mappings',
        'constants',
        'ignored_columns',
        'is_default',
        'proposed_by',
        'created_by_id',
    ];

    protected $casts = [
        'entity_type' => ImportEntityType::class,
        'mappings' => 'array',
        'constants' => 'array',
        'ignored_columns' => 'array',
        'is_default' => 'boolean',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(ImportSource::class, 'source_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
