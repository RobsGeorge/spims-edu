<?php

namespace App\Models;

use App\Enums\LiveQuizSessionState;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiveQuizSession extends Model
{
    use HasUlids;

    protected $fillable = [
        'quiz_id',
        'join_code',
        'state',
        'current_question_id',
        'question_opened_at',
        'question_closes_at',
        'host_id',
    ];

    protected $casts = [
        'state' => LiveQuizSessionState::class,
        'question_opened_at' => 'datetime',
        'question_closes_at' => 'datetime',
    ];

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(LiveQuiz::class, 'quiz_id');
    }

    public function currentQuestion(): BelongsTo
    {
        return $this->belongsTo(LiveQuizQuestion::class, 'current_question_id');
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(LiveQuizParticipant::class, 'session_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(LiveQuizAnswer::class, 'session_id');
    }
}
