<?php

namespace App\Models;

use App\Enums\ProjectMembershipEventKind;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMembershipEvent extends Model
{
    use HasUlids;

    protected $fillable = [
        'project_id',
        'student_id',
        'kind',
        'meta',
    ];

    protected $casts = [
        'kind' => ProjectMembershipEventKind::class,
        'meta' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
