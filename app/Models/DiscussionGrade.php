<?php

namespace App\Models;

use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscussionGrade extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'thread_id',
        'student_id',
        'auto_score',
        'final_score',
        'overridden',
        'feedback',
        'graded_by_id',
        'graded_at',
    ];

    protected $casts = [
        'auto_score' => 'float',
        'final_score' => 'float',
        'overridden' => 'boolean',
        'graded_at' => 'datetime',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(DiscussionThread::class, 'thread_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
