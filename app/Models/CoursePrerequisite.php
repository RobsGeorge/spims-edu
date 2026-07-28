<?php

namespace App\Models;

use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoursePrerequisite extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'course_id',
        'prerequisite_id',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function prerequisite(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'prerequisite_id');
    }
}
