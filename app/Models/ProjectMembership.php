<?php

namespace App\Models;

use App\Enums\ProjectMembershipRole;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMembership extends Model
{
    use HasUlids;

    protected $fillable = [
        'project_id',
        'student_id',
        'role',
        'joined_at',
        'left_at',
    ];

    protected $casts = [
        'role' => ProjectMembershipRole::class,
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function isActive(): bool
    {
        return $this->left_at === null;
    }
}
