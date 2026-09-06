<?php

namespace App\Models;

use App\Enums\ProjectDeliverableKind;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectDeliverable extends Model
{
    use HasUlids;

    protected $fillable = [
        'phase_id',
        'kind',
        'title',
        'max_files',
        'max_file_mb',
        'due_at',
        'points',
    ];

    protected $casts = [
        'kind' => ProjectDeliverableKind::class,
        'max_files' => 'integer',
        'max_file_mb' => 'integer',
        'due_at' => 'datetime',
        'points' => 'float',
    ];

    public function phase(): BelongsTo
    {
        return $this->belongsTo(ProjectPhase::class, 'phase_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(ProjectDeliverableSubmission::class, 'deliverable_id');
    }
}
