<?php

namespace App\Models;

use App\Enums\ImportSourceKind;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportSource extends Model
{
    use HasUlids;

    protected $fillable = [
        'code',
        'name',
        'kind',
        'precedence',
        'gpa_scale_max',
        'default_currency',
        'timezone',
        'active',
    ];

    protected $casts = [
        'kind' => ImportSourceKind::class,
        'precedence' => 'integer',
        'gpa_scale_max' => 'float',
        'active' => 'boolean',
    ];

    public function gradeMappings(): HasMany
    {
        return $this->hasMany(ImportGradeMapping::class, 'source_id');
    }

    public function mappingProfiles(): HasMany
    {
        return $this->hasMany(ImportMappingProfile::class, 'source_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ImportBatch::class, 'source_id');
    }
}
