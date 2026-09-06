<?php

namespace App\Models;

use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiveQuizQuestion extends Model
{
    use HasUlids;

    protected $fillable = [
        'quiz_id',
        'prompt',
        'position',
        'time_limit_seconds',
        'points',
    ];

    protected $casts = [
        'position' => 'integer',
        'time_limit_seconds' => 'integer',
        'points' => 'integer',
    ];

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(LiveQuiz::class, 'quiz_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(LiveQuizOption::class, 'question_id')->orderBy('position');
    }
}
