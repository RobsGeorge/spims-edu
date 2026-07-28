<?php

namespace App\Models;

use App\Enums\ComponentKind;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentTemplateComponent extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'template_id',
        'name',
        'weight_percent',
        'kind',
    ];

    protected $casts = [
        'weight_percent' => 'float',
        'kind' => ComponentKind::class,
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(AssessmentTemplate::class, 'template_id');
    }
}
