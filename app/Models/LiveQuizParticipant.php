<?php

namespace App\Models;

use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiveQuizParticipant extends Model
{
    use HasUlids;

    protected $fillable = [
        'session_id',
        'student_id',
        'display_name',
        'joined_at',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(LiveQuizSession::class, 'session_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(LiveQuizAnswer::class, 'participant_id');
    }
}
