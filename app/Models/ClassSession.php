<?php

namespace App\Models;

use App\Enums\ClassSessionMode;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassSession extends Model
{
    use HasUlids;

    protected $fillable = [
        'offering_id',
        'title',
        'scheduled_start',
        'duration_minutes',
        'mode',
        'location',
        'live_session_id',
        'attendance_closed_at',
        'notify_students',
        'lock_version',
    ];

    protected $casts = [
        'mode' => ClassSessionMode::class,
        'scheduled_start' => 'datetime',
        'duration_minutes' => 'integer',
        'attendance_closed_at' => 'datetime',
        'notify_students' => 'boolean',
        'lock_version' => 'integer',
    ];

    public function offering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class, 'offering_id');
    }

    public function liveSession(): BelongsTo
    {
        return $this->belongsTo(LiveSession::class, 'live_session_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(AttendanceEntry::class);
    }

    public function checkInCodes(): HasMany
    {
        return $this->hasMany(AttendanceCheckInCode::class);
    }

    public function notificationTargets(): HasMany
    {
        return $this->hasMany(SessionNotificationTarget::class);
    }

    public function isClosed(): bool
    {
        return $this->attendance_closed_at !== null;
    }
}
