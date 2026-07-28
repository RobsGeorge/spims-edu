<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\RefundStatus;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'payment_id',
        'enrollment_id',
        'student_id',
        'amount_minor',
        'currency',
        'as_points',
        'status',
        'reason',
        'requested_by_id',
        'approved_by_id',
    ];

    protected $casts = [
        'currency' => Currency::class,
        'status' => RefundStatus::class,
        'amount_minor' => 'integer',
        'as_points' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
