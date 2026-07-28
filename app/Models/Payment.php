<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'student_id',
        'invoice_id',
        'currency',
        'amount_minor',
        'method',
        'status',
        'gateway_ref',
        'proof_url',
        'recorded_by_id',
        'verified_by_id',
        'receipt_serial',
        'receipt_url',
    ];

    protected $casts = [
        'currency' => Currency::class,
        'method' => PaymentMethod::class,
        'status' => PaymentStatus::class,
        'amount_minor' => 'integer',
        'created_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
