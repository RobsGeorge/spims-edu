<?php

namespace App\Models;

use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiveSession extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'offering_id',
        'title',
        'scheduled_start',
        'duration_minutes',
        'zoom_meeting_id',
        'zoom_join_url',
        'zoom_start_url',
        'recording_url',
        'reminder_24h_sent_at',
        'reminder_15m_sent_at',
    ];

    protected $casts = [
        'scheduled_start' => 'datetime',
        'duration_minutes' => 'integer',
        'reminder_24h_sent_at' => 'datetime',
        'reminder_15m_sent_at' => 'datetime',
    ];

    public function offering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class, 'offering_id');
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function endsAt(): \Carbon\CarbonInterface
    {
        return $this->scheduled_start->copy()->addMinutes($this->duration_minutes);
    }

    public function isJoinable(?\Carbon\CarbonInterface $now = null): bool
    {
        $now ??= now();
        $open = $this->scheduled_start->copy()->subMinutes(15);
        $close = $this->endsAt();

        return $now->betweenIncluded($open, $close);
    }
}
