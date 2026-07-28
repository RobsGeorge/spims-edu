<?php

namespace App\Models;

use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SessionRecurrence extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'offering_id',
        'days_of_week',
        'start_time',
        'duration_minutes',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'days_of_week' => 'array',
        'duration_minutes' => 'integer',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    public function offering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class, 'offering_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(LiveSession::class, 'offering_id', 'offering_id');
    }
}
