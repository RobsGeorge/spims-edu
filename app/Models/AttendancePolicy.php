<?php

namespace App\Models;

use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendancePolicy extends Model
{
    use HasUlids;

    protected $table = 'attendance_policy';

    protected $fillable = [
        'offering_id',
        'min_percentage',
        'late_grade_percentage',
        'counts_toward_grade',
        'is_enabled',
    ];

    protected $casts = [
        'min_percentage' => 'integer',
        'late_grade_percentage' => 'integer',
        'counts_toward_grade' => 'boolean',
        'is_enabled' => 'boolean',
    ];

    public function offering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class, 'offering_id');
    }

    public function isGlobal(): bool
    {
        return $this->offering_id === null;
    }
}
