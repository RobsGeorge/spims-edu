<?php

namespace App\Models;

use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveQuizAnswer extends Model
{
    use HasUlids;

    protected $fillable = [
        'session_id',
        'question_id',
        'participant_id',
        'option_id',
        'answered_at',
        'score',
    ];

    protected $casts = [
        'answered_at' => 'datetime',
        'score' => 'integer',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(LiveQuizSession::class, 'session_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(LiveQuizQuestion::class, 'question_id');
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(LiveQuizParticipant::class, 'participant_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(LiveQuizOption::class, 'option_id');
    }
}
