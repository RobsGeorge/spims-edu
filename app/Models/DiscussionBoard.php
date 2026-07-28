<?php

namespace App\Models;

use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiscussionBoard extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'offering_id',
        'allow_student_threads',
    ];

    protected $casts = [
        'allow_student_threads' => 'boolean',
    ];

    public function offering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class, 'offering_id');
    }

    public function threads(): HasMany
    {
        return $this->hasMany(DiscussionThread::class, 'board_id');
    }
}
