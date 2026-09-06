<?php

namespace App\Models;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceEntry extends Model
{
    use HasUlids;

    protected $fillable = [
        'class_session_id',
        'student_id',
        'status',
        'excuse_reason',
        'minutes_attended',
        'source',
        'recorded_by_id',
        'recorded_at',
        'lock_version',
    ];

    protected $casts = [
        'status' => AttendanceStatus::class,
        'source' => AttendanceSource::class,
        'minutes_attended' => 'integer',
        'recorded_at' => 'datetime',
        'lock_version' => 'integer',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class, 'class_session_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }
}
