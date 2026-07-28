<?php

namespace App\Models;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    use HasUlids;

    public const CREATED_AT = null;

    protected $fillable = [
        'live_session_id',
        'student_id',
        'status',
        'minutes_attended',
        'source',
        'overridden_by_id',
    ];

    protected $casts = [
        'status' => AttendanceStatus::class,
        'source' => AttendanceSource::class,
        'minutes_attended' => 'integer',
        'updated_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(LiveSession::class, 'live_session_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
