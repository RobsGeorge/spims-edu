<?php

namespace App\Models;

use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventCheckIn extends Model
{
    use HasUlids;

    protected $fillable = [
        'reservation_id',
        'checked_in_at',
        'checked_in_by_id',
    ];

    protected $casts = [
        'checked_in_at' => 'datetime',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(EventReservation::class, 'reservation_id');
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by_id');
    }
}
