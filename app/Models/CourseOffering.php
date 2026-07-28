<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\OfferingMode;
use App\Enums\OfferingStatus;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourseOffering extends Model
{
    use HasUlids;
    use SoftDeletes;

    protected $fillable = [
        'course_id',
        'semester_id',
        'mode',
        'price_usd_override',
        'price_egp_override',
        'seat_capacity',
        'attendance_threshold_percent',
        'status',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'mode' => OfferingMode::class,
        'status' => OfferingStatus::class,
        'price_usd_override' => 'integer',
        'price_egp_override' => 'integer',
        'seat_capacity' => 'integer',
        'attendance_threshold_percent' => 'float',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(OfferingStaff::class, 'offering_id');
    }

    public function weeks(): HasMany
    {
        return $this->hasMany(Week::class, 'offering_id')->orderBy('order');
    }

    public function resolvedPriceUsd(): int
    {
        if ($this->course->is_free) {
            return 0;
        }

        return $this->price_usd_override ?? $this->course->default_price_usd;
    }

    public function resolvedPriceEgp(): int
    {
        if ($this->course->is_free) {
            return 0;
        }

        return $this->price_egp_override ?? $this->course->default_price_egp;
    }

    public function resolvedPriceForCountry(?string $countryCode): array
    {
        $currency = strtoupper((string) $countryCode) === 'EG' ? Currency::Egp : Currency::Usd;

        return [
            'currency' => $currency,
            'amount_minor' => $currency === Currency::Egp
                ? $this->resolvedPriceEgp()
                : $this->resolvedPriceUsd(),
        ];
    }
}
