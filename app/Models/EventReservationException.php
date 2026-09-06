<?php

namespace App\Models;

use App\Enums\EventReservationExceptionKind;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventReservationException extends Model
{
    use HasUlids;

    protected $fillable = [
        'event_id',
        'student_id',
        'kind',
    ];

    protected $casts = [
        'kind' => EventReservationExceptionKind::class,
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
