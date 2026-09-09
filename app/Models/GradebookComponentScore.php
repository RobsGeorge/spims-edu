<?php

namespace App\Models;

use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Direct staff-entered percentage override for a student+component pair.
 * When present, this value replaces the computed component percent for that student.
 */
class GradebookComponentScore extends Model
{
    use HasUlids;

    protected $fillable = [
        'component_id',
        'student_id',
        'score',
        'updated_by_id',
    ];

    protected $casts = [
        'score' => 'float',
    ];

    public function component(): BelongsTo
    {
        return $this->belongsTo(GradebookComponent::class, 'component_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }
}
