<?php

namespace App\Models;

use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectSubmissionFile extends Model
{
    use HasUlids;

    protected $fillable = [
        'submission_id',
        'path',
        'original_name',
        'size_bytes',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ProjectDeliverableSubmission::class, 'submission_id');
    }
}
