<?php

namespace App\Models;

use App\Enums\OfferingStatus;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Semester extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'academic_year_id',
        'name',
        'start_date',
        'end_date',
        'registration_start',
        'registration_end',
        'add_drop_end_week',
        'last_withdrawal_week',
        'withdrawal_refund_percent',
        'status',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'registration_start' => 'datetime',
        'registration_end' => 'datetime',
        'add_drop_end_week' => 'integer',
        'last_withdrawal_week' => 'integer',
        'withdrawal_refund_percent' => 'float',
        'status' => OfferingStatus::class,
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function offerings(): HasMany
    {
        return $this->hasMany(CourseOffering::class);
    }

    public function isRegistrationOpen(?\DateTimeInterface $at = null): bool
    {
        $at = $at ?? now();

        return $at >= $this->registration_start && $at <= $this->registration_end;
    }
}
