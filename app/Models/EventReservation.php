<?php

namespace App\Models;

use App\Enums\EventReservationStatus;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EventReservation extends Model
{
    use HasUlids;

    protected $fillable = [
        'event_id',
        'student_id',
        'status',
        'reserved_at',
        'cancelled_at',
    ];

    protected $casts = [
        'status' => EventReservationStatus::class,
        'reserved_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function checkIn(): HasOne
    {
        return $this->hasOne(EventCheckIn::class, 'reservation_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [
            EventReservationStatus::Reserved,
            EventReservationStatus::Waitlisted,
        ], true);
    }
}
