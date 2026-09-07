<?php

namespace App\Models;

use App\Enums\AdvisingHoldKind;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdvisingHold extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'student_id',
        'kind',
        'reason',
        'created_by_id',
        'released_at',
        'released_by_id',
        'created_at',
    ];

    protected $casts = [
        'kind' => AdvisingHoldKind::class,
        'released_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (AdvisingHold $hold): void {
            $hold->created_at ??= now();
        });
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('released_at');
    }

    public function isActive(): bool
    {
        return $this->released_at === null;
    }
}
