<?php

namespace App\Models;

use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModuleStudentAssessment extends Model
{
    use HasUlids;

    /**
     * Rating scale is 1-5 ("needs attention" ... "excellent"), stored as an
     * unsigned tinyint. Documented here rather than inferred from a magic number
     * scattered across services/views.
     */
    public const RATING_MIN = 1;

    public const RATING_MAX = 5;

    protected $fillable = [
        'week_id',
        'student_id',
        'rating',
        'comment',
        'assessed_by_id',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    public function week(): BelongsTo
    {
        return $this->belongsTo(Week::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function assessedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by_id');
    }
}
