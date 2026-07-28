<?php

namespace App\Models;

use App\Enums\ComponentKind;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GradebookComponent extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'offering_id',
        'name',
        'weight_percent',
        'kind',
    ];

    protected $casts = [
        'weight_percent' => 'float',
        'kind' => ComponentKind::class,
    ];

    public function offering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class, 'offering_id');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class, 'component_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class, 'component_id');
    }
}
