<?php

namespace App\Models;

use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradeBand extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'scheme_id',
        'letter',
        'min_percent',
        'max_percent',
        'gpa_points',
        'is_passing',
    ];

    protected $casts = [
        'min_percent' => 'float',
        'max_percent' => 'float',
        'gpa_points' => 'float',
        'is_passing' => 'boolean',
    ];

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(GradingScheme::class, 'scheme_id');
    }
}
