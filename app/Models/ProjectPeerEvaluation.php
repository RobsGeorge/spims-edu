<?php

namespace App\Models;

use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectPeerEvaluation extends Model
{
    use HasUlids;

    protected $fillable = [
        'project_id',
        'rater_id',
        'ratee_id',
        'score',
        'comment',
        'submitted_at',
    ];

    protected $casts = [
        'score' => 'float',
        'submitted_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function rater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rater_id');
    }

    public function ratee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ratee_id');
    }
}
