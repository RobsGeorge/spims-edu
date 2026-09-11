<?php

namespace App\Models;

use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportGradeMapping extends Model
{
    use HasUlids;

    protected $fillable = [
        'source_id',
        'legacy_letter',
        'min_percent',
        'max_percent',
        'spims_letter',
        'gpa_points',
        'is_passing',
        'counts_toward_gpa',
    ];

    protected $casts = [
        'min_percent' => 'float',
        'max_percent' => 'float',
        'gpa_points' => 'float',
        'is_passing' => 'boolean',
        'counts_toward_gpa' => 'boolean',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(ImportSource::class, 'source_id');
    }
}
