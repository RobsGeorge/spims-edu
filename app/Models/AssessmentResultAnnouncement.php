<?php

namespace App\Models;

use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentResultAnnouncement extends Model
{
    use HasUlids;

    protected $fillable = [
        'assessment_id',
        'announced_at',
        'announced_by_id',
        'notified_student_ids',
    ];

    protected $casts = [
        'announced_at' => 'datetime',
        'notified_student_ids' => 'array',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function announcedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'announced_by_id');
    }
}
