<?php

namespace App\Models;

use App\Enums\EventReservationStatus;
use App\Enums\EventStatus;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use HasUlids;

    protected $fillable = [
        'title',
        'description',
        'starts_at',
        'ends_at',
        'venue',
        'capacity',
        'status',
        'eligibility',
        'waitlist_enabled',
        'created_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'capacity' => 'integer',
        'status' => EventStatus::class,
        'eligibility' => 'array',
        'waitlist_enabled' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(EventReservation::class);
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(EventReservationException::class);
    }

    public function admins(): HasMany
    {
        return $this->hasMany(EventAdmin::class);
    }

    public function isPublished(): bool
    {
        return $this->status === EventStatus::Published;
    }

    public function reservedCount(): int
    {
        return $this->reservations()
            ->where('status', EventReservationStatus::Reserved)
            ->count();
    }
}
